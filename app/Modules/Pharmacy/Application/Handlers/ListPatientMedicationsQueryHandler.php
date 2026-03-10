<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PatientMedicationData;
use App\Modules\Pharmacy\Application\Queries\ListPatientMedicationsQuery;
use App\Modules\Pharmacy\Application\Services\PatientMedicationViewService;

final class ListPatientMedicationsQueryHandler
{
    public function __construct(
        private readonly PatientMedicationViewService $patientMedicationViewService,
    ) {}

    /**
     * @return list<PatientMedicationData>
     */
    public function handle(ListPatientMedicationsQuery $query): array
    {
        return $this->patientMedicationViewService->list($query->patientId, $query->criteria);
    }
}
