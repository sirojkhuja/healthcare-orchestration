<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class RemoveAppointmentParticipantCommand
{
    public function __construct(
        public string $appointmentId,
        public string $participantId,
    ) {}
}
