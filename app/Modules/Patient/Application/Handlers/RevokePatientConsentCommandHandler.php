<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\RevokePatientConsentCommand;
use App\Modules\Patient\Application\Data\PatientConsentData;
use App\Modules\Patient\Application\Services\PatientConsentService;

final class RevokePatientConsentCommandHandler
{
    public function __construct(
        private readonly PatientConsentService $patientConsentService,
    ) {}

    public function handle(RevokePatientConsentCommand $command): PatientConsentData
    {
        return $this->patientConsentService->revoke($command->patientId, $command->consentId, $command->reason);
    }
}
