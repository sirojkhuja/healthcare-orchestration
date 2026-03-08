<?php

namespace App\Modules\IdentityAccess\Domain\Users;

final class TenantUserStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const LOCKED = 'locked';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::LOCKED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function creatable(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return match ([$from, $to]) {
            [self::INACTIVE, self::ACTIVE],
            [self::ACTIVE, self::INACTIVE],
            [self::ACTIVE, self::LOCKED],
            [self::LOCKED, self::ACTIVE] => true,
            default => false,
        };
    }
}
