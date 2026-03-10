<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class ConfirmAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
