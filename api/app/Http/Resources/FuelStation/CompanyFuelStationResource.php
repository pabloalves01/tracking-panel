<?php

namespace App\Http\Resources\FuelStation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyFuelStationResource extends JsonResource
{
    /**
     * Vínculo como sai na API. O id interno não é exposto.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cnpj' => $this->cnpj,
            'status' => $this->status,
            'fuel_station' => $this->fuelStation ? [
                'uuid' => $this->fuelStation->uuid,
                'corporate_name' => $this->fuelStation->corporate_name,
                'trade_name' => $this->fuelStation->trade_name,
            ] : null,
            'company' => [
                'uuid' => $this->company?->uuid,
                'legal_name' => $this->company?->legal_name,
                'trade_name' => $this->company?->trade_name,
                'cnpj' => $this->company?->cnpj,
            ],
        ];
    }
}
