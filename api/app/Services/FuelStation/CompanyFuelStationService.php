<?php

namespace App\Services\FuelStation;

use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Rules\Cnpj;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CompanyFuelStationService
{
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
        if ($cnpj === '') {
            throw ValidationException::withMessages([
                'cnpj' => 'O CNPJ é obrigatório',
            ]);
        }

        if (! Cnpj::isValid($cnpj)) {
            throw ValidationException::withMessages([
                'cnpj' => 'Informe um CNPJ válido',
            ]);
        }

        $station = FuelStation::query()
            ->with(['city', 'state'])
            ->where('cnpj', $cnpj)
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
        $station = FuelStation::query()->where('cnpj', $cnpj)->first();

        if (! $station) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este posto não possui cadastro no Logiv',
            ]);
        }

        try {
            CompanyFuelStation::query()->create([
                'company_id' => app(CurrentCompany::class)->id,
                'fuel_station_id' => $station->id,
                'cnpj' => $cnpj,
                'status' => 'request_send',
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Este CNPJ já foi solicitado');
        }
    }

    public function destroy(string $cnpj): void
    {
        $this->companyLink($cnpj)->delete();
    }

    /**
     * @return Collection<int, CompanyFuelStation>
     */
    public function listForStation(): Collection
    {
        return CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->with(['fuelStation', 'company'])
            ->latest()
            ->get();
    }

    public function accept(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'request_send') {
            abort(422, 'Este pedido não está pendente');
        }

        $link->update(['status' => 'active']);
    }

    public function reject(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status === 'rejected') {
            abort(422, 'Este pedido já foi recusado');
        }

        if ($link->status !== 'request_send') {
            abort(422, 'Este pedido não está pendente');
        }

        $link->update(['status' => 'rejected']);
    }

    public function disconnect(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'active') {
            abort(422, 'Este vínculo não está ativo');
        }

        $link->update(['status' => 'disconnected']);
    }

    private function companyLink(string $cnpj): CompanyFuelStation
    {
        $link = CompanyFuelStation::query()
            ->where('company_id', app(CurrentCompany::class)->id)
            ->where('cnpj', $cnpj)
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }

    private function stationLink(string $companyUuid): CompanyFuelStation
    {
        $link = CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->whereHas('company', fn ($query) => $query->where('uuid', $companyUuid))
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }

    public function resendRequest(string $cnpj): void
    {
        $link = $this->companyLink($cnpj);

        if (! in_array($link->status, ['rejected', 'disconnected'], true)) {
            abort(422, 'Esta solicitação não pode ser reenviada');
        }

        $link->update(['status' => 'request_send']);
    }
}
