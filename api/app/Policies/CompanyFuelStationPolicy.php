<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use App\Support\Membership;

class CompanyFuelStationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role() !== null;
    }

    public function manage(User $user): bool
    {
        return Membership::manages($this->role());
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
