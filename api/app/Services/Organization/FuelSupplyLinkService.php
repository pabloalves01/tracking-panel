<?php

namespace App\Services\Organization;

use App\Exceptions\NotFound;
use App\Enums\OrganizationType;
use App\Models\FuelSupplyLink;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Support\Text;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O convênio entre uma empresa e um posto.
 *
 *   [*] → request_sent → active → disconnected
 *          │        └→ rejected      │
 *          └→ (empresa cancela) [*]  │
 *                 reenvia ←──────────┘
 *
 * Toda transição é um update condicional: a linha só muda se ainda estiver
 * no estado esperado, então dois cliques simultâneos não a levam para dois
 * lugares diferentes.
 */
class FuelSupplyLinkService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, FuelSupplyLink> */
    public function listForCompany(Organization $company): Collection
    {
        return FuelSupplyLink::query()
            ->where('company_id', $company->getKey())
            ->with('station.city', 'station.state')
            ->latest('id')
            ->get();
    }

    /** O posto só vê quem pediu ou está conveniado. */
    public function listForStation(Organization $station): Collection
    {
        return FuelSupplyLink::query()
            ->where('station_id', $station->getKey())
            ->whereIn('status', [FuelSupplyLink::REQUEST_SENT, FuelSupplyLink::ACTIVE])
            ->with('company')
            ->latest('id')
            ->get();
    }

    /** Procura um posto pelo CNPJ para a empresa pedir o convênio. */
    public function findStationByDocument(string $document): ?Organization
    {
        return Organization::query()
            ->fuelStations()
            ->where('document', Text::digits($document))
            ->with(['city', 'state'])
            ->first();
    }

    public function request(Organization $company, string $document): FuelSupplyLink
    {
        return DB::transaction(function () use ($company, $document) {
            $digits = Text::digits($document);

            if ($digits === $company->document) {
                throw new DomainException('Este CNPJ é o da sua própria empresa');
            }

            $station = $this->findStationByDocument($digits);

            if (! $station) {
                throw new DomainException(
                    'Nenhum posto encontrado com esse CNPJ. Peça ao posto para se cadastrar e depois vincule aqui.'
                );
            }

            if (! $station->isActive()) {
                throw new DomainException('Este posto está inativo no sistema');
            }

            $link = FuelSupplyLink::query()
                ->where('company_id', $company->getKey())
                ->where('station_id', $station->getKey())
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                $link = FuelSupplyLink::query()->create([
                    'company_id' => $company->getKey(),
                    'station_id' => $station->getKey(),
                    'status' => FuelSupplyLink::REQUEST_SENT,
                ]);
            } else {
                $this->assertCanResend($link);
                $link->update(['status' => FuelSupplyLink::REQUEST_SENT]);
            }

            $this->audit->log('fuel_supply_link.requested', $link, [
                'station_id' => $station->getKey(),
            ], $company->getKey());

            return $link->load('station.city', 'station.state');
        });
    }

    /** A empresa cancela um pedido que o posto ainda não respondeu. */
    public function cancel(Organization $company, string $uuid): void
    {
        $this->transition(
            $this->findForCompany($company, $uuid),
            from: [FuelSupplyLink::REQUEST_SENT],
            to: null,
            action: 'fuel_supply_link.cancelled',
            organizationId: $company->getKey(),
            error: 'Só dá para cancelar um pedido que ainda não foi respondido',
        );
    }

    public function accept(Organization $station, string $uuid): void
    {
        $this->transition(
            $this->findForStation($station, $uuid),
            from: [FuelSupplyLink::REQUEST_SENT],
            to: FuelSupplyLink::ACTIVE,
            action: 'fuel_supply_link.accepted',
            organizationId: $station->getKey(),
            error: 'Este pedido já foi respondido',
        );
    }

    public function reject(Organization $station, string $uuid): void
    {
        $this->transition(
            $this->findForStation($station, $uuid),
            from: [FuelSupplyLink::REQUEST_SENT],
            to: FuelSupplyLink::REJECTED,
            action: 'fuel_supply_link.rejected',
            organizationId: $station->getKey(),
            error: 'Este pedido já foi respondido',
        );
    }

    /** Qualquer um dos dois lados encerra um convênio ativo. */
    public function disconnect(Organization $organization, string $uuid): void
    {
        $link = $organization->type === OrganizationType::Company
            ? $this->findForCompany($organization, $uuid)
            : $this->findForStation($organization, $uuid);

        $this->transition(
            $link,
            from: [FuelSupplyLink::ACTIVE],
            to: FuelSupplyLink::DISCONNECTED,
            action: 'fuel_supply_link.disconnected',
            organizationId: $organization->getKey(),
            error: 'Só dá para encerrar um convênio ativo',
        );
    }

    // ================================ INTERNALS ================================

    /**
     * @param  array<int, string>  $from
     */
    private function transition(
        FuelSupplyLink $link,
        array $from,
        ?string $to,
        string $action,
        int $organizationId,
        string $error,
    ): void {
        DB::transaction(function () use ($link, $from, $to, $action, $organizationId, $error) {
            // Update condicional: se o estado mudou entre a leitura e a
            // escrita, nenhuma linha é afetada e a transição é recusada.
            $changed = FuelSupplyLink::query()
                ->whereKey($link->getKey())
                ->whereIn('status', $from)
                ->when($to === null,
                    fn ($query) => $query->delete(),
                    fn ($query) => $query->update(['status' => $to]),
                );

            if ($changed === 0) {
                throw new DomainException($error);
            }

            $this->audit->log($action, $link, [], $organizationId);
        });
    }

    private function assertCanResend(FuelSupplyLink $link): void
    {
        $resendable = [FuelSupplyLink::REJECTED, FuelSupplyLink::DISCONNECTED];

        if (in_array($link->status, $resendable, true)) {
            return;
        }

        throw new DomainException(match ($link->status) {
            FuelSupplyLink::REQUEST_SENT => 'Já existe um pedido aguardando resposta deste posto',
            FuelSupplyLink::ACTIVE => 'Este posto já está conveniado',
            default => 'Não é possível pedir convênio a este posto agora',
        });
    }

    private function findForCompany(Organization $company, string $uuid): FuelSupplyLink
    {
        return FuelSupplyLink::query()
            ->where('company_id', $company->getKey())
            ->where('uuid', $uuid)
            ->firstOr(fn () => throw new NotFound('Convênio não encontrado'));
    }

    private function findForStation(Organization $station, string $uuid): FuelSupplyLink
    {
        return FuelSupplyLink::query()
            ->where('station_id', $station->getKey())
            ->where('uuid', $uuid)
            ->firstOr(fn () => throw new NotFound('Convênio não encontrado'));
    }
}
