<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\DeviceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceModel
 */
class DeviceModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manufacturer' => $this->manufacturer,
            'name' => $this->name,
            'label' => $this->label(),
        ];
    }
}
