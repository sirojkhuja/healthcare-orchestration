<?php

namespace App\Modules\Scheduling\Domain\Availability;

final class AvailabilityScopeType
{
    public const DATE = 'date';

    public const WEEKLY = 'weekly';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::WEEKLY,
            self::DATE,
        ];
    }
}
