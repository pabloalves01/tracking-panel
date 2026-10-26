<?php

namespace App\Support;

class CurrentFuelStation
{
    public function __construct(public int $id, public string $role = Membership::OWNER) {}
}
