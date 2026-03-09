<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\UpdatePatientCommand;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Services\PatientAdministrationService;

final class UpdatePatientCommandHandler
{
    public function __construct(
        private readonly PatientAdministrationService $patientAdministrationService,
    ) {}

    public function handle(UpdatePatientCommand $command): PatientData
    {
        return $this->patientAdministrationService->update($command->patientId, $command->attributes);
    }
}
