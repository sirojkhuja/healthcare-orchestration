<?php

namespace App\Modules\TenantManagement\Domain\Clinics;

final class ClinicStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }

    public static function canActivate(string $status): bool
    {
        return $status === self::INACTIVE;
    }

    public static function canDeactivate(string $status): bool
    {
        return $status === self::ACTIVE;
    }

    public static function canDelete(string $status): bool
    {
        return $status === self::INACTIVE;
    }
}
