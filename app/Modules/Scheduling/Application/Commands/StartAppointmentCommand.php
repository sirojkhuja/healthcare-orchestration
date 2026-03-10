<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class StartAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
