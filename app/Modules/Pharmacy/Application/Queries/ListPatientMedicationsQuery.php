<?php

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Pharmacy\Application\Data\PatientMedicationListCriteria;

final readonly class ListPatientMedicationsQuery
{
    public function __construct(
        public string $patientId,
        public PatientMedicationListCriteria $criteria,
    ) {}
}
