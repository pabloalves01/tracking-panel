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

    /**
     * Preenchido na resposta do convidante. Não vai para o preview público.
     */
    public ?bool $accountExists = null;

    protected $fillable = [
        'organization_id',
        'email',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
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
