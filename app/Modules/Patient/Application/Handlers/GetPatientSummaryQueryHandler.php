<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientSummaryData;
use App\Modules\Patient\Application\Queries\GetPatientSummaryQuery;
use App\Modules\Patient\Application\Services\PatientReadService;

final class GetPatientSummaryQueryHandler
{
    public function __construct(
        private readonly PatientReadService $patientReadService,
    ) {}

    public function handle(GetPatientSummaryQuery $query): PatientSummaryData
    {
        return $this->patientReadService->summary($query->patientId);
    }
}
