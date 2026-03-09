<?php

namespace App\Modules\Patient\Application\Contracts;

use App\Modules\Patient\Application\Data\PatientContactData;

interface PatientContactRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $patientId, array $attributes): PatientContactData;

    public function findInTenant(string $tenantId, string $patientId, string $contactId): ?PatientContactData;

    /**
     * @return list<PatientContactData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $patientId, string $contactId, array $updates): ?PatientContactData;

    public function delete(string $tenantId, string $patientId, string $contactId): bool;

    public function clearPrimaryForPatient(string $tenantId, string $patientId, ?string $ignoreContactId = null): void;
}
