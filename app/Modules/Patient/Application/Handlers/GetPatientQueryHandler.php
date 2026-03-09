<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Queries\GetPatientQuery;
use App\Modules\Patient\Application\Services\PatientAdministrationService;

final class GetPatientQueryHandler
{
    public function __construct(
        private readonly PatientAdministrationService $patientAdministrationService,
    ) {}

    public function handle(GetPatientQuery $query): PatientData
    {
        return $this->patientAdministrationService->get($query->patientId);
    }
}
