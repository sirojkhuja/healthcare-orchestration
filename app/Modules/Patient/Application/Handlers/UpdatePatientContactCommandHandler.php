<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\UpdatePatientContactCommand;
use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Services\PatientContactService;

final class UpdatePatientContactCommandHandler
{
    public function __construct(
        private readonly PatientContactService $patientContactService,
    ) {}

    public function handle(UpdatePatientContactCommand $command): PatientContactData
    {
        return $this->patientContactService->update(
            $command->patientId,
            $command->contactId,
            $command->attributes,
        );
    }
}
