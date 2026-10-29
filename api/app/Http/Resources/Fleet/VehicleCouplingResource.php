<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\VehicleCoupling;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um período de engate.
 *
 * @mixin VehicleCoupling
 */
class VehicleCouplingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'traction' => ['uuid' => $this->traction->uuid, 'plate' => $this->traction->plate],
            'trailer' => ['uuid' => $this->trailer->uuid, 'plate' => $this->trailer->plate],
            'position' => $this->position,
            'coupled_at' => $this->coupled_at->toIso8601String(),
            'uncoupled_at' => $this->uncoupled_at?->toIso8601String(),
        ];
    }
}
