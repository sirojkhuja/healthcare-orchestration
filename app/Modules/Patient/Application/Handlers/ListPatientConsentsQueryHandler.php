<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Data\PatientConsentData;
use App\Modules\Patient\Application\Queries\ListPatientConsentsQuery;
use App\Modules\Patient\Application\Services\PatientConsentService;

final class ListPatientConsentsQueryHandler
{
    public function __construct(
        private readonly PatientConsentService $patientConsentService,
    ) {}

    /**
     * @return list<PatientConsentData>
     */
    public function handle(ListPatientConsentsQuery $query): array
    {
        return $this->patientConsentService->list($query->patientId);
    }
}
