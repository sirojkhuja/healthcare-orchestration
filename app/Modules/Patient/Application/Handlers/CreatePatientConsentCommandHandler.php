<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\CreatePatientConsentCommand;
use App\Modules\Patient\Application\Data\PatientConsentData;
use App\Modules\Patient\Application\Services\PatientConsentService;

final class CreatePatientConsentCommandHandler
{
    public function __construct(
        private readonly PatientConsentService $patientConsentService,
    ) {}

    public function handle(CreatePatientConsentCommand $command): PatientConsentData
    {
        return $this->patientConsentService->create($command->patientId, $command->attributes);
    }
}
