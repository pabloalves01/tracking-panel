<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use App\Support\Membership;
use Illuminate\Auth\Access\Response;

class MembershipPolicy
{
    public function viewAny(User $user): Response
    {
        return Membership::manages($this->role())
            ? Response::allow()
            : Response::deny('Você não gerencia os usuários deste contexto.');
    }

    public function manage(User $user): Response
    {
        return Membership::manages($this->role())
            ? Response::allow()
            : Response::deny('Você não gerencia os usuários deste contexto.');
    }

    private function role(): ?string
    {
        if (app()->bound(CurrentCompany::class)) {
            return app(CurrentCompany::class)->role;
        }

        if (app()->bound(CurrentFuelStation::class)) {
            return app(CurrentFuelStation::class)->role;
        }

        return null;
    }
}
