<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleType
 */
class VehicleTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'category' => $this->category->value,
        ];
    }
}
