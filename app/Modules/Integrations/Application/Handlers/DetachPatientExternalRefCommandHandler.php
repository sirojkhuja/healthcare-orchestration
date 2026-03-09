<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\DetachPatientExternalRefCommand;
use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;
use App\Modules\Integrations\Application\Services\PatientExternalReferenceService;

final class DetachPatientExternalRefCommandHandler
{
    public function __construct(
        private readonly PatientExternalReferenceService $patientExternalReferenceService,
    ) {}

    public function handle(DetachPatientExternalRefCommand $command): PatientExternalReferenceData
    {
        return $this->patientExternalReferenceService->delete($command->patientId, $command->referenceId);
    }
}
