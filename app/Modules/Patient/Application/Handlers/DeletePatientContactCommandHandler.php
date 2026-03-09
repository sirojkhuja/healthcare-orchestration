<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\DeletePatientContactCommand;
use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Services\PatientContactService;

final class DeletePatientContactCommandHandler
{
    public function __construct(
        private readonly PatientContactService $patientContactService,
    ) {}

    public function handle(DeletePatientContactCommand $command): PatientContactData
    {
        return $this->patientContactService->delete($command->patientId, $command->contactId);
    }
}
