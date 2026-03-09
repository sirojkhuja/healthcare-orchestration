<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class GetPatientDocumentQuery
{
    public function __construct(
        public string $patientId,
        public string $documentId,
    ) {}
}
