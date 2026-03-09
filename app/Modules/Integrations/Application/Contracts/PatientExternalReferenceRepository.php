<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;

interface PatientExternalReferenceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $patientId, array $attributes): PatientExternalReferenceData;

    public function delete(string $tenantId, string $patientId, string $referenceId): bool;

    public function existsDuplicate(
        string $tenantId,
        string $patientId,
        string $integrationKey,
        string $externalType,
        string $externalId,
    ): bool;

    public function findInTenant(string $tenantId, string $patientId, string $referenceId): ?PatientExternalReferenceData;

    /**
     * @return list<PatientExternalReferenceData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;
}
