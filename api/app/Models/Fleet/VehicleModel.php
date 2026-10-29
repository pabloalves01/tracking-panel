<?php

namespace App\Models\Fleet;

use App\Models\Fleet\Concerns\SharedCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de veículo, de uma marca.
 */
class VehicleModel extends Model
{
    use SharedCatalog;

    protected $fillable = [
        'company_id',
        'vehicle_brand_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'vehicle_brand_id' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'vehicle_brand_id');
    }
}
