<?php

namespace App\Models\Registry;

use App\Enums\EntityRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Um papel exercido por uma entidade. A situação é por papel: alguém pode
 * deixar de ser funcionário e continuar motorista.
 */
class EntityRoleAssignment extends Model
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $table = 'entity_roles';

    protected $fillable = [
        'entity_id',
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => EntityRole::class,
        ];
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
