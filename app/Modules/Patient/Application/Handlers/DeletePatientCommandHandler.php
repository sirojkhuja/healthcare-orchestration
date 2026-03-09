<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\DeletePatientCommand;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Services\PatientAdministrationService;

final class DeletePatientCommandHandler
{
    public function __construct(
        private readonly PatientAdministrationService $patientAdministrationService,
    ) {}

    public function handle(DeletePatientCommand $command): PatientData
    {
        return $this->patientAdministrationService->delete($command->patientId);
    }
}
