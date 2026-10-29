<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\DeviceInstallation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um período de instalação.
 *
 * @mixin DeviceInstallation
 */
class DeviceInstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'vehicle' => ['uuid' => $this->vehicle->uuid, 'plate' => $this->vehicle->plate],
            'role' => $this->role->value,
            'installed_at' => $this->installed_at->toIso8601String(),
            'removed_at' => $this->removed_at?->toIso8601String(),
        ];
    }
}
