<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\CreatePatientCommand;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Services\PatientAdministrationService;

final class CreatePatientCommandHandler
{
    public function __construct(
        private readonly PatientAdministrationService $patientAdministrationService,
    ) {}

    public function handle(CreatePatientCommand $command): PatientData
    {
        return $this->patientAdministrationService->create($command->attributes);
    }
}
