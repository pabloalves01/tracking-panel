<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Model;

/**
 * Os dados que só o motorista tem.
 */
class DriverDetails extends Model
{
    protected $table = 'driver_details';

    protected $fillable = [
        'entity_id',
        'license_number',
        'license_category',
        'license_expires_on',
        'license_issued_state',
        'residence_proof_path',
    ];

    protected function casts(): array
    {
        return [
            'license_expires_on' => 'date',
        ];
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
