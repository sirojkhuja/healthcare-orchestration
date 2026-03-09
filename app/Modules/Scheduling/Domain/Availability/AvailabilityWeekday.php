<?php

namespace App\Modules\Scheduling\Domain\Availability;

use Carbon\CarbonImmutable;

final class AvailabilityWeekday
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];
    }

    public static function fromDate(CarbonImmutable $date): string
    {
        return strtolower($date->englishDayOfWeek);
    }
}
