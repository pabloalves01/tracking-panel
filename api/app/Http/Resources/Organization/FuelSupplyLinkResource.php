<?php

namespace App\Http\Resources\Organization;

use App\Http\Resources\OrganizationResource;
use App\Models\FuelSupplyLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FuelSupplyLink
 */
class FuelSupplyLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'requested_at' => $this->created_at,
            'station' => $this->whenLoaded('station', fn () => OrganizationResource::make($this->station)),
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
