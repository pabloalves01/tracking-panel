<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O vínculo entre uma conta e uma organização.
 *
 * Sem coluna de papel: por enquanto todo membro tem o mesmo acesso.
 */
class Membership extends Model
{
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
