<?php

namespace App\Models\Concerns;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Restringe o model à organização ativa.
 *
 * A coluna continua se chamando `company_id` porque é a empresa dona do
 * cadastro — um posto não tem motoristas nem clientes próprios.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $query->where(
                $query->getModel()->getTable().'.company_id',
                self::currentOrganization()->id()
            );
        });

        static::creating(function ($model) {
            $model->company_id ??= self::currentOrganization()->id();
        });
    }

    /**
     * Fora do contexto de uma organização — num job, num comando — resolver
     * a classe pelo container levantava `BindingResolutionException`. O erro
     * agora diz o que de fato aconteceu.
     */
    private static function currentOrganization(): CurrentOrganization
    {
        if (! app()->bound(CurrentOrganization::class)) {
            throw new LogicException(sprintf(
                '%s só pode ser consultado dentro do contexto de uma organização.',
                static::class
            ));
        }

        return app(CurrentOrganization::class);
    }
}
