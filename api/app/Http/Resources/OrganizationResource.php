<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'document' => $this->document,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'display_name' => $this->displayName(),
            'status' => $this->status,
            'home_path' => $this->type->homePath(),
            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'state' => $this->whenLoaded('state', fn () => $this->state?->uf ?? $this->state?->name),
            'city' => $this->whenLoaded('city', fn () => $this->city?->name),
        ];
    }
}
