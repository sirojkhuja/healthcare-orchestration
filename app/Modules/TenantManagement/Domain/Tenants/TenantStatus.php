<?php

namespace App\Modules\TenantManagement\Domain\Tenants;

final class TenantStatus
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::SUSPENDED,
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return match ($from) {
            self::ACTIVE => $to === self::SUSPENDED,
            self::SUSPENDED => $to === self::ACTIVE,
            default => false,
        };
    }

    public static function isDeletable(string $status): bool
    {
        return $status === self::SUSPENDED;
    }
}
