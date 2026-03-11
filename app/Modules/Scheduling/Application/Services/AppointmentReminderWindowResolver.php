<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\Scheduling\Application\Data\AppointmentData;
use Carbon\CarbonImmutable;

final class AppointmentReminderWindowResolver
{
    public function resolve(AppointmentData $appointment): string
    {
        $now = CarbonImmutable::now($appointment->timezone);
        $start = $appointment->scheduledStartAt->setTimezone($appointment->timezone);
        $days = (int) $now->startOfDay()->diffInDays($start->startOfDay(), false);

        return match (true) {
            $days <= 0 => 'same_day',
            $days === 1 => 'day_before',
            default => 'advance',
        };
    }
}
