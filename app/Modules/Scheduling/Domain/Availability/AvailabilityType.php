<?php

namespace App\Modules\Scheduling\Domain\Availability;

final class AvailabilityType
{
    public const AVAILABLE = 'available';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::AVAILABLE,
            self::UNAVAILABLE,
        ];
    }
}
