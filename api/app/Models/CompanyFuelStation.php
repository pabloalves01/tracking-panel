<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFuelStation extends Model
{
    use HasUuids;

    protected $table = 'company_fuel_station';

    protected $fillable = [
        'company_id',
        'fuel_station_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'fuel_station_id' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ===================== BELONGS TO RELATIONS =====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fuelStation(): BelongsTo
    {
        // Posto excluído ainda tem nome para a lista da empresa.
        return $this->belongsTo(FuelStation::class)->withTrashed();
    }
}
