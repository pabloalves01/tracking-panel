<?php

namespace App\Models;

use App\Models\Location\City;
use App\Models\Location\State;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'companies';

    protected $fillable = [
        'legal_name',
        'trade_name',
        'cnpj',
        'logo_path',
        'status',
        'state_id',
        'city_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Postos que a empresa já pode usar: só o vínculo aceito.
     */
    public function fuelStations(): BelongsToMany
    {
        return $this->belongsToMany(FuelStation::class, 'company_fuel_station')
            ->wherePivot('status', 'accepted');
    }

    // ===================== UNIQUE IDS =====================

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // ===================== ROUTE KEY NAME =====================

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
