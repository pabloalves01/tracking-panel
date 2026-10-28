<?php

namespace App\Models\Concerns;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $query->where(
                $query->getModel()->getTable().'.company_id',
                self::currentCompany()->id
            );
        });

        static::creating(function ($model) {
            $model->company_id ??= self::currentCompany()->id;
        });
    }

    /**
     * O contexto é registrado pelo middleware de empresa. Fora dele — num job,
     * num comando, no contexto de posto — resolver a classe pelo container
     * levantava `BindingResolutionException`, porque o construtor exige um
     * `int`. O erro agora diz o que de fato aconteceu.
     */
    private static function currentCompany(): CurrentCompany
    {
        if (! app()->bound(CurrentCompany::class)) {
            throw new LogicException(sprintf(
                '%s só pode ser consultado dentro do contexto de uma empresa.',
                static::class
            ));
        }

        return app(CurrentCompany::class);
    }
}
