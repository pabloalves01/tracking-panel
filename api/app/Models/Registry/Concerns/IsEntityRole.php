<?php

namespace App\Models\Registry\Concerns;

use App\Enums\EntityRole;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Registry\Entity;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O que todo papel tem: a pessoa por trás dele, a situação, o escopo por
 * empresa e a exclusão lógica. O model do papel declara só o que é dele —
 * colunas próprias, casts e regras de domínio.
 */
trait IsEntityRole
{
    use BelongsToCompany;
    use HasUuids;
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    abstract public function role(): EntityRole;

    public function initializeIsEntityRole(): void
    {
        $this->mergeFillable(['entity_id', 'status']);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /**
     * Busca pelo nome ou documento da pessoa.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (Text::plain($term) === '') {
            return $query;
        }

        return $query->whereHas('entity', fn (Builder $entity) => $entity->search($term));
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status === null ? $query : $query->where($this->qualifyColumn('status'), $status);
    }

    /**
     * Ordena pelo nome da pessoa, que mora em outra tabela.
     */
    public function scopeOrderByName(Builder $query): Builder
    {
        return $query->orderBy(
            Entity::query()->select('name')->whereColumn('entities.id', $this->qualifyColumn('entity_id'))
        );
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
