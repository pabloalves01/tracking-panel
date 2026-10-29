<?php

namespace App\Http\Resources\Fleet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item de marca ou modelo. `custom`: acrescentado pela empresa, não da
 * lista da LogIV.
 */
class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'custom' => ! $this->isGlobal(),
        ];
    }
}
