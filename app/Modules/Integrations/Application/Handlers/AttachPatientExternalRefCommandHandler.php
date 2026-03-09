<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\AttachPatientExternalRefCommand;
use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;
use App\Modules\Integrations\Application\Services\PatientExternalReferenceService;

final class AttachPatientExternalRefCommandHandler
{
    public function __construct(
        private readonly PatientExternalReferenceService $patientExternalReferenceService,
    ) {}

    public function handle(AttachPatientExternalRefCommand $command): PatientExternalReferenceData
    {
        return $this->patientExternalReferenceService->attach($command->patientId, $command->attributes);
    }
}
