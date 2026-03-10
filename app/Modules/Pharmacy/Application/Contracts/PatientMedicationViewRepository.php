<?php

namespace App\Modules\Pharmacy\Application\Contracts;

use App\Modules\Pharmacy\Application\Data\PatientMedicationData;
use App\Modules\Pharmacy\Application\Data\PatientMedicationListCriteria;

interface PatientMedicationViewRepository
{
    /**
     * @return list<PatientMedicationData>
     */
    public function listForPatient(
        string $tenantId,
        string $patientId,
        PatientMedicationListCriteria $criteria,
    ): array;
}
