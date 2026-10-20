<?php

namespace App\Models\Concerns;

use App\Support\CurrentCompany;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany()
    {
        static::addGlobalScope('company', function ($query) {
            $query->where(
                $query->getModel()->getTable() . '.company_id',
                app(CurrentCompany::class)->id
            );
        });

        static::creating(function ($model) {
            $model->company_id ??= app(CurrentCompany::class)->id;
        });
    }
}
