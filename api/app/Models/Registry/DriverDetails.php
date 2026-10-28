<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * UF e categoria em maiúsculas, venham de onde vierem.
     *
     * Sem isto, "sp" e "SP" viram dois valores para o mesmo estado e um
     * filtro por UF perde as linhas de quem digitou minúsculo.
     */
    protected function licenseIssuedState(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)));
    }

    protected function licenseCategory(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)));
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
