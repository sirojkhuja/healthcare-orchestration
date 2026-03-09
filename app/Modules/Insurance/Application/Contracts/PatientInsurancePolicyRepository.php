<?php

namespace App\Modules\Insurance\Application\Contracts;

use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;

interface PatientInsurancePolicyRepository
{
    public function clearPrimaryForPatient(string $tenantId, string $patientId, ?string $ignorePolicyId = null): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $patientId, array $attributes): PatientInsurancePolicyData;

    public function delete(string $tenantId, string $patientId, string $policyId): bool;

    public function existsDuplicate(string $tenantId, string $patientId, string $insuranceCode, string $policyNumber): bool;

    public function findInTenant(string $tenantId, string $patientId, string $policyId): ?PatientInsurancePolicyData;

    /**
     * @return list<PatientInsurancePolicyData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;
}
