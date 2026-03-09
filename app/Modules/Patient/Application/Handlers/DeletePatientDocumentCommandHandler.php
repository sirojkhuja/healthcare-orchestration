<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\DeletePatientDocumentCommand;
use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Services\PatientDocumentService;

final class DeletePatientDocumentCommandHandler
{
    public function __construct(
        private readonly PatientDocumentService $patientDocumentService,
    ) {}

    public function handle(DeletePatientDocumentCommand $command): PatientDocumentData
    {
        return $this->patientDocumentService->delete($command->patientId, $command->documentId);
    }
}
