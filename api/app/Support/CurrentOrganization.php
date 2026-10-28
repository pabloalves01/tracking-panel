<?php

namespace App\Support;

use App\Models\Organization;

/**
 * A organização ativa da requisição, registrada pelo middleware.
 *
 * Substitui `CurrentCompany` e `CurrentFuelStation`. O papel padrão que
 * `CurrentCompany` trazia — `admin` quando nenhum era informado — não existe
 * mais: não há papéis, e um padrão que concede acesso é uma armadilha.
 */
class CurrentOrganization
{
    public function __construct(public readonly Organization $organization) {}

    public function id(): int
    {
        return $this->organization->getKey();
    }

    public function isCompany(): bool
    {
        return $this->organization->type === \App\Enums\OrganizationType::Company;
    }
}
