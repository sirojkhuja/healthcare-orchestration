<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CancelAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
        public string $reason,
    ) {}
}
