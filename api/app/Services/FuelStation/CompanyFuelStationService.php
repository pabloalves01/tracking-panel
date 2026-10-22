<?php

namespace App\Services\FuelStation;

use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
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
                'status' => 'pending',
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

        if ($link->status !== 'pending') {
            abort(422, 'Este pedido não está pendente');
        }

        $link->update(['status' => 'accepted']);
    }

    public function reject(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if (! in_array($link->status, ['pending', 'accepted'], true)) {
            abort(422, 'Este pedido já foi recusado');
        }

        $link->update(['status' => 'rejected']);
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
}
