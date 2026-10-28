<?php

namespace App\Http\Resources\Organization;

use App\Http\Resources\OrganizationResource;
use App\Models\FuelStationAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FuelStationAgreement
 */
class FuelStationAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'requested_at' => $this->created_at,
            'fuel_station' => $this->whenLoaded('fuelStation', fn () => OrganizationResource::make($this->fuelStation)),
            'company' => $this->whenLoaded('company', fn () => OrganizationResource::make($this->company)),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'request_sent' => 'Aguardando resposta',
            'active' => 'Conveniado',
            'rejected' => 'Recusado',
            'disconnected' => 'Encerrado',
            default => $this->status,
        };
    }
}
