<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\FuelStation\FuelStationResource;
use App\Http\Resources\System\Company\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Usuário autenticado. A senha fica de fora.
     * `current_company` e `current_fuel_station` vêm do token desta sessão.
     * O token carrega um contexto só: empresa ou posto.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $companyId = $this->currentAccessToken()?->company_id;
        $stationId = $this->currentAccessToken()?->fuel_station_id;
        $company = $companyId ? $this->companies()->whereKey($companyId)->first() : null;
        $station = $stationId
            ? $this->fuelStations()->with(['city', 'state'])->whereKey($stationId)->first()
            : null;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_system_admin' => (bool) $this->is_system_admin,
            'has_company' => (bool) $company,
            'current_company' => $company ? CompanyResource::make($company) : null,
            'has_fuel_station' => (bool) $station,
            'current_fuel_station' => $station ? FuelStationResource::make($station) : null,
        ];
    }
}
