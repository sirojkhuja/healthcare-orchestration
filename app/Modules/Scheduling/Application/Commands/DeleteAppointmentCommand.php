<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class DeleteAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
