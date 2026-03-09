<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Queries\GetPatientDocumentQuery;
use App\Modules\Patient\Application\Services\PatientDocumentService;

final class GetPatientDocumentQueryHandler
{
    public function __construct(
        private readonly PatientDocumentService $patientDocumentService,
    ) {}

    public function handle(GetPatientDocumentQuery $query): PatientDocumentData
    {
        return $this->patientDocumentService->get($query->patientId, $query->documentId);
    }
}
