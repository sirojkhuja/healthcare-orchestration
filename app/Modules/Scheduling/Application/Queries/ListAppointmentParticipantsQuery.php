<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class ListAppointmentParticipantsQuery
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
