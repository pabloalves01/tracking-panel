<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelStationUser extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'fuel_station_user';

    protected $fillable = [
        'fuel_station_id',
        'user_id',
        'role',
        'status',
        'invited_by',
    ];

    // ===================== BELONGS TO RELATIONS =====================

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
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
