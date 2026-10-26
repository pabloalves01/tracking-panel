<?php

namespace App\Support;

class MembershipScope
{
    /**
     * @param  class-string  $membershipClass
     */
    public function __construct(
        public string $membershipClass,
        public string $foreignKey,
        public int $ownerId,
        public string $actorRole,
        public ?int $companyId,
        public ?int $fuelStationId,
    ) {}
}
