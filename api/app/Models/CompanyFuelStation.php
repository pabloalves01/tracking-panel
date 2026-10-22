<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFuelStation extends Model
{
    protected $table = 'company_fuel_station';

    protected $fillable = [
        'company_id',
        'fuel_station_id',
        'cnpj',
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
