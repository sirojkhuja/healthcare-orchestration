<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class GetAppointmentAuditQuery
{
    public function __construct(
        public string $appointmentId,
        public int $limit = 50,
    ) {}
}
