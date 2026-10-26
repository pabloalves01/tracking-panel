<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasUuids;

    /** Papéis de empresa e posto. */
    public const ROLES = ['admin', 'member'];

    /** Validade do link, em dias. */
    public const VALID_DAYS = 7;

    protected $fillable = [
        'company_id',
        'fuel_station_id',
        'email',
        'role',
        'token_hash',
        'status',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'fuel_station_id' => 'integer',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    // ===================== BELONGS TO RELATIONS =====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class)->withTrashed();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ===================== UNIQUE IDS =====================

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
