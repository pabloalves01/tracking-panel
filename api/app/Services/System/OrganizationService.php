<?php

namespace App\Services\System;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Services\Access\InvitationService;
use App\Services\AuditLogger;
use App\Support\Text;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * As organizações, do ponto de vista do admin do sistema.
 *
 * Uma empresa nasce aqui, e nasce junto com o convite do primeiro usuário:
 * o admin do sistema não entra na empresa nem cria conta por ela.
 */
class OrganizationService
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly AuditLogger $audit,
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $search = Text::plain($filters['search'] ?? '');

        return Organization::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $digits = Text::digits($search);

                $query->where(function ($scoped) use ($search, $digits) {
                    $scoped->where('legal_name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%");

                    if ($digits !== '') {
                        $scoped->orWhere('document', 'like', "%{$digits}%");
                    }
                });
            })
            ->with(['city', 'state'])
            ->orderBy('legal_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function get(string $uuid): Organization
    {
        return Organization::query()->where('uuid', $uuid)->with(['city', 'state'])->firstOrFail();
    }

    /**
     * Cria a empresa e convida o primeiro usuário na mesma transação.
     *
     * O e-mail do convite sai depois do commit: antes ele era disparado de
     * dentro da transação e, com fila `sync`, chegava antes de a empresa
     * existir de fato.
     */
    public function store(array $data): Organization
    {
        $organization = DB::transaction(function () use ($data) {
            $document = Text::digits($data['document']);

            if (Organization::withTrashed()->where('document', $document)->exists()) {
                throw new DomainException('Já existe uma organização com este CNPJ');
            }

            $organization = Organization::query()->create([
                'type' => OrganizationType::Company,
                'document' => $document,
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'] ?? null,
                'status' => Organization::ACTIVE,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
            ]);

            $this->audit->log('organization.created', $organization, [
                'type' => OrganizationType::Company->value,
            ], $organization->getKey());

            return $organization;
        });

        $invitation = $this->invitations->create($organization, $data['owner_email'], auth()->user());

        // A resposta é o que a tela mostra em seguida: sem carregar cidade e
        // estado, a empresa recém-criada aparece sem endereço até alguém
        // recarregar a lista.
        $organization->load(['city', 'state']);
        $organization->setRelation('firstInvitation', $invitation);

        return $organization;
    }

    public function update(string $uuid, array $data): Organization
    {
        $organization = $this->get($uuid);

        $organization->update(array_intersect_key($data, array_flip([
            'legal_name', 'trade_name', 'state_id', 'city_id',
            'postal_code', 'street', 'street_number', 'complement', 'neighborhood',
        ])));

        $this->audit->log('organization.updated', $organization, [], $organization->getKey());

        return $organization->fresh(['city', 'state']);
    }

    public function setStatus(string $uuid, string $status): void
    {
        $organization = $this->get($uuid);
        $organization->update(['status' => $status]);

        $this->audit->log(
            $status === Organization::ACTIVE ? 'organization.activated' : 'organization.deactivated',
            $organization,
            [],
            $organization->getKey(),
        );
    }
}
