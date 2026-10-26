<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelStationUser extends Model
{
    protected $table = 'fuel_station_user';

    protected $fillable = [
        'fuel_station_id',
        'user_id',
        'role',
        'status',
    ];

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
