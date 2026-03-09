<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;
use App\Modules\Integrations\Application\Queries\ListPatientExternalRefsQuery;
use App\Modules\Integrations\Application\Services\PatientExternalReferenceService;

final class ListPatientExternalRefsQueryHandler
{
    public function __construct(
        private readonly PatientExternalReferenceService $patientExternalReferenceService,
    ) {}

    /**
     * @return list<PatientExternalReferenceData>
     */
    public function handle(ListPatientExternalRefsQuery $query): array
    {
        return $this->patientExternalReferenceService->list($query->patientId);
    }
}
