<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class DeletePatientDocumentCommand
{
    public function __construct(
        public string $patientId,
        public string $documentId,
    ) {}
}
