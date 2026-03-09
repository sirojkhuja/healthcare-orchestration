<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\DetachPatientInsuranceCommand;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Modules\Insurance\Application\Services\PatientInsuranceService;

final class DetachPatientInsuranceCommandHandler
{
    public function __construct(
        private readonly PatientInsuranceService $patientInsuranceService,
    ) {}

    public function handle(DetachPatientInsuranceCommand $command): PatientInsurancePolicyData
    {
        return $this->patientInsuranceService->delete($command->patientId, $command->policyId);
    }
}
