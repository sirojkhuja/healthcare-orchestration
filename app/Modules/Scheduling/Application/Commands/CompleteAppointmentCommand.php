<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CompleteAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
