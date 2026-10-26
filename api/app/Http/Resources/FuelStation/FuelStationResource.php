<?php

namespace App\Http\Resources\FuelStation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelStationResource extends JsonResource
{
    /**
     * Posto como sai na API.
     * O `id` interno não é exposto; o cliente identifica o posto pelo `uuid`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'cnpj' => $this->cnpj,
            'corporate_name' => $this->corporate_name,
            'trade_name' => $this->trade_name,
            'zip_code' => $this->zip_code,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'status' => $this->status,
            'role' => $this->whenPivotLoaded('fuel_station_user', fn () => $this->pivot->role),
            'city_name' => $this->city?->name,
            'uf' => $this->state?->uf,
        ];
    }
}
