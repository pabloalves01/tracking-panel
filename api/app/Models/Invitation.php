<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const REVOKED = 'revoked';

    public const EXPIRED = 'expired';

    public const DAYS = 7;

    /**
     * Token em claro, só nesta instância, logo depois de criar ou reenviar.
     */
    public ?string $plainToken = null;

    protected $fillable = [
        'company_id',
        'fuel_station_id',
        'email',
        'role',
        'status',
        'token_hash',
        'expires_at',
        'invited_by',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function inviteUrl(?string $plainToken = null): ?string
    {
        $token = $plainToken ?? $this->plainToken;

        if ($token === null || $token === '') {
            return null;
        }

        return rtrim((string) config('app.frontend_url'), '/').'/auth/convite/'.$token;
    }
}
