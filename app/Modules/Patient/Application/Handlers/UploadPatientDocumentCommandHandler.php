<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\UploadPatientDocumentCommand;
use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Services\PatientDocumentService;

final class UploadPatientDocumentCommandHandler
{
    public function __construct(
        private readonly PatientDocumentService $patientDocumentService,
    ) {}

    public function handle(UploadPatientDocumentCommand $command): PatientDocumentData
    {
        return $this->patientDocumentService->upload(
            patientId: $command->patientId,
            file: $command->document,
            title: $command->title,
            documentType: $command->documentType,
        );
    }
}
