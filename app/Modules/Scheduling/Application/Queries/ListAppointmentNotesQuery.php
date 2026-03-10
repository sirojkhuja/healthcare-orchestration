<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class ListAppointmentNotesQuery
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
