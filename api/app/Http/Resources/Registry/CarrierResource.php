<?php

namespace App\Http\Resources\Registry;

use App\Models\Registry\Carrier;

/**
 * @mixin Carrier
 */
class CarrierResource extends EntityRoleResource
{
    protected function details(): array
    {
        return [
            'rntrc' => $this->rntrc,
            'antt_category' => $this->antt_category?->value,
            'rntrc_expires_on' => $this->rntrc_expires_on?->toDateString(),
        ];
    }
}
