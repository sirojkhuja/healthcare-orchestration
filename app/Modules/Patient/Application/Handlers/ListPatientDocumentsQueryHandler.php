<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Queries\ListPatientDocumentsQuery;
use App\Modules\Patient\Application\Services\PatientDocumentService;

final class ListPatientDocumentsQueryHandler
{
    public function __construct(
        private readonly PatientDocumentService $patientDocumentService,
    ) {}

    /**
     * @return list<PatientDocumentData>
     */
    public function handle(ListPatientDocumentsQuery $query): array
    {
        return $this->patientDocumentService->list($query->patientId);
    }
}
