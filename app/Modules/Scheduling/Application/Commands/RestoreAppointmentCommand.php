<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class RestoreAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
