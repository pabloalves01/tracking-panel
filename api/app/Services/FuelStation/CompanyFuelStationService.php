<?php

namespace App\Services\FuelStation;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Rules\Cnpj;
use App\Services\AuditLogger;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyFuelStationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return Collection<int, CompanyFuelStation>
     */
    public function listForCompany(): Collection
    {
        return CompanyFuelStation::query()
            ->where('company_id', app(CurrentCompany::class)->id)
            ->with(['fuelStation', 'company'])
            ->latest()
            ->get();
    }

    /**
     * Posto inativo continua na consulta. Bloquear inativo ainda não foi decidido.
     */
    public function findByCnpj(string $cnpj): FuelStation
    {
        $digits = Cnpj::digits($cnpj);

        if ($digits === '') {
            throw ValidationException::withMessages([
                'cnpj' => 'O CNPJ é obrigatório',
            ]);
        }

        if (! Cnpj::isValid($digits)) {
            throw ValidationException::withMessages([
                'cnpj' => 'Informe um CNPJ válido',
            ]);
        }

        $station = FuelStation::query()
            ->with(['city', 'state'])
            ->where('cnpj', $digits)
            ->first();

        if (! $station) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este posto não possui cadastro no Logiv',
            ]);
        }

        return $station;
    }

    public function store(string $cnpj): void
    {
        $digits = Cnpj::digits($cnpj);
        $company = Company::query()->findOrFail(app(CurrentCompany::class)->id);

        if (Cnpj::digits($company->cnpj) === $digits) {
            throw ValidationException::withMessages([
                'cnpj' => 'A empresa não pode convidar o próprio CNPJ',
            ]);
        }

        $station = FuelStation::query()->where('cnpj', $digits)->first();

        if (! $station) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este posto não possui cadastro no Logiv',
            ]);
        }

        try {
            DB::transaction(function () use ($company, $station) {
                $link = CompanyFuelStation::query()->create([
                    'company_id' => $company->id,
                    'fuel_station_id' => $station->id,
                    'status' => 'request_sent',
                ]);

                $this->audit->log('company_fuel_station.requested', $link, [
                    'cnpj' => $station->cnpj,
                ], $company->id, $station->id);
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Este CNPJ já foi solicitado');
        }
    }

    public function destroy(string $stationUuid): string
    {
        $link = $this->companyLinkByStationUuid($stationUuid);

        if ($link->status !== 'request_sent') {
            abort(422, 'Só é possível cancelar um convite pendente');
        }

        DB::transaction(function () use ($link) {
            $deleted = CompanyFuelStation::query()
                ->whereKey($link->id)
                ->where('status', 'request_sent')
                ->delete();

            if ($deleted === 0) {
                abort(422, 'Só é possível cancelar um convite pendente');
            }

            $this->audit->log('company_fuel_station.canceled', $link, [], $link->company_id, $link->fuel_station_id);
        });

        return 'Pedido removido';
    }

    public function disconnectForCompany(string $stationUuid): void
    {
        $link = $this->companyLinkByStationUuid($stationUuid);

        if ($link->status !== 'active') {
            abort(422, 'Este vínculo não está ativo');
        }

        $this->transition($link, 'active', 'disconnected', 'Este vínculo não está ativo', 'company_fuel_station.disconnected');
    }

    public function resendRequest(string $stationUuid): void
    {
        $link = $this->companyLinkByStationUuid($stationUuid);

        if (! in_array($link->status, ['rejected', 'disconnected'], true)) {
            abort(422, 'Este convite não pode ser reenviado');
        }

        $station = $link->fuelStation;

        if (! $station || $station->trashed()) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este posto não possui cadastro no Logiv',
            ]);
        }

        $this->transition($link, $link->status, 'request_sent', 'Este convite não pode ser reenviado', 'company_fuel_station.resent');
    }

    /**
     * @return Collection<int, CompanyFuelStation>
     */
    public function listForStation(): Collection
    {
        return CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->with(['fuelStation', 'company'])
            ->latest()
            ->get();
    }

    public function accept(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'request_sent') {
            abort(422, 'Este pedido não está pendente');
        }

        if (! $link->company || $link->company->status !== 'active') {
            abort(422, 'Esta empresa não está disponível');
        }

        $this->transition($link, 'request_sent', 'active', 'Este pedido não está pendente', 'company_fuel_station.accepted');
    }

    public function reject(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status === 'rejected') {
            abort(422, 'Este pedido já foi recusado');
        }

        if ($link->status !== 'request_sent') {
            abort(422, 'Este pedido não está pendente');
        }

        $this->transition($link, 'request_sent', 'rejected', 'Este pedido não está pendente', 'company_fuel_station.rejected');
    }

    public function disconnect(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'active') {
            abort(422, 'Este vínculo não está ativo');
        }

        $this->transition($link, 'active', 'disconnected', 'Este vínculo não está ativo', 'company_fuel_station.disconnected');
    }

    private function transition(CompanyFuelStation $link, string $from, string $to, string $failure, string $action): void
    {
        DB::transaction(function () use ($link, $from, $to, $failure, $action) {
            $updated = CompanyFuelStation::query()
                ->whereKey($link->id)
                ->where('status', $from)
                ->update([
                    'status' => $to,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, $failure);
            }

            $this->audit->log($action, $link, [
                'from' => $from,
                'to' => $to,
            ], $link->company_id, $link->fuel_station_id);
        });
    }

    private function companyLinkByStationUuid(string $stationUuid): CompanyFuelStation
    {
        if (! Str::isUuid($stationUuid)) {
            abort(404, 'Pedido não encontrado');
        }

        $link = CompanyFuelStation::query()
            ->where('company_id', app(CurrentCompany::class)->id)
            ->whereHas('fuelStation', fn ($query) => $query->withTrashed()->where('uuid', $stationUuid))
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }

    private function stationLink(string $companyUuid): CompanyFuelStation
    {
        if (! Str::isUuid($companyUuid)) {
            abort(404, 'Pedido não encontrado');
        }

        $link = CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->whereHas('company', fn ($query) => $query->where('uuid', $companyUuid))
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }
}
