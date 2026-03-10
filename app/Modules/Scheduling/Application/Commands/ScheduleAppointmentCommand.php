<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class ScheduleAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
