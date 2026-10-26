<?php

namespace App\Support;

class Membership
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const MEMBER = 'member';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [self::OWNER, self::ADMIN, self::MEMBER];
    }

    public static function manages(?string $role): bool
    {
        return in_array($role, [self::OWNER, self::ADMIN], true);
    }
}
