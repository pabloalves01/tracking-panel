<?php

namespace App\Support;

class CurrentCompany
{
    public function __construct(public int $id, public string $role = Membership::ADMIN) {}
}
