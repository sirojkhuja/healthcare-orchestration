<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class GetAppointmentQuery
{
    public function __construct(
        public string $appointmentId,
    ) {}
}
