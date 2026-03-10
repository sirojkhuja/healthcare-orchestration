<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Pharmacy\Application\Contracts\PatientMedicationViewRepository;
use App\Modules\Pharmacy\Application\Data\PatientMedicationData;
use App\Modules\Pharmacy\Application\Data\PatientMedicationListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PatientMedicationViewService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientMedicationViewRepository $patientMedicationViewRepository,
    ) {}

    /**
     * @return list<PatientMedicationData>
     */
    public function list(string $patientId, PatientMedicationListCriteria $criteria): array
    {
        $patient = $this->patientOrFail($patientId);

        return $this->patientMedicationViewRepository->listForPatient(
            $this->tenantContext->requireTenantId(),
            $patient->patientId,
            $criteria,
        );
    }

    private function patientOrFail(string $patientId): PatientData
    {
        $patient = $this->patientRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
        );

        if (! $patient instanceof PatientData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $patient;
    }
}
