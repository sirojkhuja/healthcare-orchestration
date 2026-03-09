<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\CreatePatientContactCommand;
use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Services\PatientContactService;

final class CreatePatientContactCommandHandler
{
    public function __construct(
        private readonly PatientContactService $patientContactService,
    ) {}

    public function handle(CreatePatientContactCommand $command): PatientContactData
    {
        return $this->patientContactService->create($command->patientId, $command->attributes);
    }
}
