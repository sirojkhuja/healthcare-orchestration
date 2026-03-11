<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class SendAppointmentReminderCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
