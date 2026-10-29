<?php

namespace App\Models\Fleet\Concerns;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo que mistura itens globais (`company_id` nulo, da LogIV) com os
 * que a empresa acrescentou. `visible()` devolve os dois; o de outra
 * empresa não aparece.
 */
trait SharedCatalog
{
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(fn (Builder $scoped) => $scoped
            ->whereNull($this->qualifyColumn('company_id'))
            ->orWhere($this->qualifyColumn('company_id'), app(CurrentOrganization::class)->id()));
    }

    public function isGlobal(): bool
    {
        return $this->company_id === null;
    }
}
