<?php

namespace App\Models;

use App\Models\Location\City;
use App\Models\Location\State;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelStation extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'fuel_stations';

    protected $fillable = [
        'cnpj',
        'corporate_name',
        'trade_name',
        'receita_checked_at',
        'zip_code',
        'street',
        'number',
        'complement',
        'district',
        'state_id',
        'city_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receita_checked_at' => 'datetime',
            'state_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    // ===================== BELONGS TO RELATIONS =====================

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // ===================== HAS MANY RELATIONS =====================

    public function users(): HasMany
    {
        return $this->hasMany(FuelStationUser::class);
    }

    public function companyLinks(): HasMany
    {
        return $this->hasMany(CompanyFuelStation::class);
    }

    // ===================== UNIQUE IDS =====================

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
