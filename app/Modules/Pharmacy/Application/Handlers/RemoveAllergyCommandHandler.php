<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\RemoveAllergyCommand;
use App\Modules\Pharmacy\Application\Data\PatientAllergyData;
use App\Modules\Pharmacy\Application\Services\PatientAllergyService;

final class RemoveAllergyCommandHandler
{
    public function __construct(
        private readonly PatientAllergyService $patientAllergyService,
    ) {}

    public function handle(RemoveAllergyCommand $command): PatientAllergyData
    {
        return $this->patientAllergyService->delete($command->patientId, $command->allergyId);
    }
}
