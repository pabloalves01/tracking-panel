<?php

namespace App\Models\Fleet;

use App\Enums\Fleet\VehicleKind;
use App\Models\Fleet\Concerns\SharedCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Marca de automotor ou de implemento.
 */
class VehicleBrand extends Model
{
    use SharedCatalog;

    protected $fillable = [
        'company_id',
        'kind',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'kind' => VehicleKind::class,
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }
}
