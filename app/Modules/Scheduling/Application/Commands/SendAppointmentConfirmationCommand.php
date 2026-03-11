<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class SendAppointmentConfirmationCommand
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
