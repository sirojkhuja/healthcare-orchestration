<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\AddAllergyCommand;
use App\Modules\Pharmacy\Application\Data\PatientAllergyData;
use App\Modules\Pharmacy\Application\Services\PatientAllergyService;

final class AddAllergyCommandHandler
{
    public function __construct(
        private readonly PatientAllergyService $patientAllergyService,
    ) {}

    public function handle(AddAllergyCommand $command): PatientAllergyData
    {
        return $this->patientAllergyService->create($command->patientId, $command->attributes);
    }
}
