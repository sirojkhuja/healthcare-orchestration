<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\AttachPatientInsuranceCommand;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Modules\Insurance\Application\Services\PatientInsuranceService;

final class AttachPatientInsuranceCommandHandler
{
    public function __construct(
        private readonly PatientInsuranceService $patientInsuranceService,
    ) {}

    public function handle(AttachPatientInsuranceCommand $command): PatientInsurancePolicyData
    {
        return $this->patientInsuranceService->attach($command->patientId, $command->attributes);
    }
}
