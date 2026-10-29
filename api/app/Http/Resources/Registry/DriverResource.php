<?php

namespace App\Http\Resources\Registry;

use App\Models\Registry\Driver;

/**
 * @mixin Driver
 */
class DriverResource extends EntityRoleResource
{
    protected function details(): array
    {
        return [
            'license_number' => $this->license_number,
            'license_category' => $this->license_category,
            'license_expires_on' => $this->license_expires_on?->toDateString(),
            'license_issued_state' => $this->license_issued_state,
            'residence_proof_path' => $this->residence_proof_path,
        ];
    }
}
