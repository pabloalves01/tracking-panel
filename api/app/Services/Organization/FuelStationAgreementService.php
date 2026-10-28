<?php

namespace App\Services\Organization;

use App\Exceptions\NotFound;
use App\Enums\OrganizationType;
use App\Models\FuelStationAgreement;
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
class FuelStationAgreementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, FuelStationAgreement> */
    public function listForCompany(Organization $company): Collection
    {
        return FuelStationAgreement::query()
            ->where('company_id', $company->getKey())
            ->with('fuelStation.city', 'fuelStation.state')
            ->latest('id')
            ->get();
    }

    /** O posto só vê quem pediu ou está conveniado. */
    public function listForFuelStation(Organization $fuelStation): Collection
    {
        return FuelStationAgreement::query()
            ->where('fuel_station_id', $fuelStation->getKey())
            ->whereIn('status', [FuelStationAgreement::REQUEST_SENT, FuelStationAgreement::ACTIVE])
            ->with('company')
            ->latest('id')
            ->get();
    }

    /** Procura um posto pelo CNPJ para a empresa pedir o convênio. */
    public function findFuelStationByDocument(string $document): ?Organization
    {
        return Organization::query()
            ->fuelStations()
            ->where('document', Text::digits($document))
            ->with(['city', 'state'])
            ->first();
    }

    public function request(Organization $company, string $document): FuelStationAgreement
    {
        return DB::transaction(function () use ($company, $document) {
            $digits = Text::digits($document);

            if ($digits === $company->document) {
                throw new DomainException('Este CNPJ é o da sua própria empresa');
            }

            $fuelStation = $this->findFuelStationByDocument($digits);

            if (! $fuelStation) {
                throw new DomainException(
                    'Nenhum posto encontrado com esse CNPJ. Peça ao posto para se cadastrar e depois vincule aqui.'
                );
            }

            if (! $fuelStation->isActive()) {
                throw new DomainException('Este posto está inativo no sistema');
            }

            $link = FuelStationAgreement::query()
                ->where('company_id', $company->getKey())
                ->where('fuel_station_id', $fuelStation->getKey())
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                $link = FuelStationAgreement::query()->create([
                    'company_id' => $company->getKey(),
                    'fuel_station_id' => $fuelStation->getKey(),
                    'status' => FuelStationAgreement::REQUEST_SENT,
                ]);
            } else {
                $this->assertCanResend($link);
                $link->update(['status' => FuelStationAgreement::REQUEST_SENT]);
            }

            $this->audit->log('fuel_station_agreement.requested', $link, [
                'fuel_station_id' => $fuelStation->getKey(),
            ], $company->getKey());

            return $link->load('fuelStation.city', 'fuelStation.state');
        });
    }

    /** A empresa cancela um pedido que o posto ainda não respondeu. */
    public function cancel(Organization $company, string $uuid): void
    {
        $this->transition(
            $this->findForCompany($company, $uuid),
            from: [FuelStationAgreement::REQUEST_SENT],
            to: null,
            action: 'fuel_station_agreement.cancelled',
            organizationId: $company->getKey(),
            error: 'Só dá para cancelar um pedido que ainda não foi respondido',
        );
    }

    public function accept(Organization $fuelStation, string $uuid): void
    {
        $this->transition(
            $this->findForFuelStation($fuelStation, $uuid),
            from: [FuelStationAgreement::REQUEST_SENT],
            to: FuelStationAgreement::ACTIVE,
            action: 'fuel_station_agreement.accepted',
            organizationId: $fuelStation->getKey(),
            error: 'Este pedido já foi respondido',
        );
    }

    public function reject(Organization $fuelStation, string $uuid): void
    {
        $this->transition(
            $this->findForFuelStation($fuelStation, $uuid),
            from: [FuelStationAgreement::REQUEST_SENT],
            to: FuelStationAgreement::REJECTED,
            action: 'fuel_station_agreement.rejected',
            organizationId: $fuelStation->getKey(),
            error: 'Este pedido já foi respondido',
        );
    }

    /** Qualquer um dos dois lados encerra um convênio ativo. */
    public function disconnect(Organization $organization, string $uuid): void
    {
        $link = $organization->type === OrganizationType::Company
            ? $this->findForCompany($organization, $uuid)
            : $this->findForFuelStation($organization, $uuid);

        $this->transition(
            $link,
            from: [FuelStationAgreement::ACTIVE],
            to: FuelStationAgreement::DISCONNECTED,
            action: 'fuel_station_agreement.disconnected',
            organizationId: $organization->getKey(),
            error: 'Só dá para encerrar um convênio ativo',
        );
    }

    // ================================ INTERNALS ================================

    /**
     * @param  array<int, string>  $from
     */
    private function transition(
        FuelStationAgreement $link,
        array $from,
        ?string $to,
        string $action,
        int $organizationId,
        string $error,
    ): void {
        DB::transaction(function () use ($link, $from, $to, $action, $organizationId, $error) {
            // Update condicional: se o estado mudou entre a leitura e a
            // escrita, nenhuma linha é afetada e a transição é recusada.
            $changed = FuelStationAgreement::query()
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

    private function assertCanResend(FuelStationAgreement $link): void
    {
        $resendable = [FuelStationAgreement::REJECTED, FuelStationAgreement::DISCONNECTED];

        if (in_array($link->status, $resendable, true)) {
            return;
        }

        throw new DomainException(match ($link->status) {
            FuelStationAgreement::REQUEST_SENT => 'Já existe um pedido aguardando resposta deste posto',
            FuelStationAgreement::ACTIVE => 'Este posto já está conveniado',
            default => 'Não é possível pedir convênio a este posto agora',
        });
    }

    private function findForCompany(Organization $company, string $uuid): FuelStationAgreement
    {
        return FuelStationAgreement::query()
            ->where('company_id', $company->getKey())
            ->where('uuid', $uuid)
            ->firstOr(fn () => throw new NotFound('Convênio não encontrado'));
    }

    private function findForFuelStation(Organization $fuelStation, string $uuid): FuelStationAgreement
    {
        return FuelStationAgreement::query()
            ->where('fuel_station_id', $fuelStation->getKey())
            ->where('uuid', $uuid)
            ->firstOr(fn () => throw new NotFound('Convênio não encontrado'));
    }
}
