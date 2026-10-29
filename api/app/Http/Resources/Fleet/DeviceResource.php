<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'imei' => $this->imei,
            'tracking_id' => $this->tracking_id,
            'sim_number' => $this->sim_number,
            'sim_iccid' => $this->sim_iccid,
            'status' => $this->status,
            'model' => DeviceModelResource::make($this->whenLoaded('deviceModel')),
            'vehicle' => $this->whenLoaded('installation', fn () => $this->installation ? [
                'uuid' => $this->installation->vehicle->uuid,
                'plate' => $this->installation->vehicle->plate,
                'role' => $this->installation->role->value,
                'installed_at' => $this->installation->installed_at->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
