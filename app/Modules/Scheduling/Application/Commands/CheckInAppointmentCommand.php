<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CheckInAppointmentCommand
{
    public function __construct(
        public string $appointmentId,
        public bool $adminOverride = false,
    ) {}
}
