<?php

namespace App\Models\Fleet;

use App\Enums\Fleet\VehicleCategory;
use App\Enums\Fleet\VehicleKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de veículo. Catálogo global, mantido pela LogIV; o grupo e a
 * categoria do veículo saem daqui.
 */
class VehicleType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'kind',
        'category',
        'mdfe_code',
    ];

    protected function casts(): array
    {
        return [
            'kind' => VehicleKind::class,
            'category' => VehicleCategory::class,
        ];
    }
}
