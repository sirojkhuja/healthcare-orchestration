<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\Patient\Application\Queries\GetPatientTimelineQuery;
use App\Modules\Patient\Application\Services\PatientReadService;

final class GetPatientTimelineQueryHandler
{
    public function __construct(
        private readonly PatientReadService $patientReadService,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function handle(GetPatientTimelineQuery $query): array
    {
        return $this->patientReadService->timeline($query->patientId, $query->limit);
    }
}
