<?php

namespace App\Services\FuelStation;

use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

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

        try {
            CompanyFuelStation::query()->create([
                'company_id' => app(CurrentCompany::class)->id,
                'fuel_station_id' => $station?->id,
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
}
