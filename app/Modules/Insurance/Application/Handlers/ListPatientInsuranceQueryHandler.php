<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Modules\Insurance\Application\Queries\ListPatientInsuranceQuery;
use App\Modules\Insurance\Application\Services\PatientInsuranceService;

final class ListPatientInsuranceQueryHandler
{
    public function __construct(
        private readonly PatientInsuranceService $patientInsuranceService,
    ) {}

    /**
     * @return list<PatientInsurancePolicyData>
     */
    public function handle(ListPatientInsuranceQuery $query): array
    {
        return $this->patientInsuranceService->list($query->patientId);
    }
}
