<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Queries\GetAppointmentAuditQuery;
use App\Modules\Scheduling\Application\Services\AppointmentReadService;

final class GetAppointmentAuditQueryHandler
{
    public function __construct(
        private readonly AppointmentReadService $appointmentReadService,
    ) {}

    /**
     * @return list<\App\Modules\AuditCompliance\Application\Data\AuditEventData>
     */
    public function handle(GetAppointmentAuditQuery $query): array
    {
        return $this->appointmentReadService->audit($query->appointmentId, $query->limit);
    }
}
