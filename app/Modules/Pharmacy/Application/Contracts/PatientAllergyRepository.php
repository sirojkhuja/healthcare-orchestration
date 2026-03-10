<?php

namespace App\Modules\Pharmacy\Application\Contracts;

use App\Modules\Pharmacy\Application\Data\PatientAllergyData;

interface PatientAllergyRepository
{
    /**
     * @param  array{
     *     medication_id: ?string,
     *     allergen_name: string,
     *     reaction: ?string,
     *     severity: ?string,
     *     noted_at: ?\Carbon\CarbonImmutable,
     *     notes: ?string
     * }  $attributes
     */
    public function create(string $tenantId, string $patientId, array $attributes): PatientAllergyData;

    public function delete(string $tenantId, string $patientId, string $allergyId): bool;

    public function allergenExists(
        string $tenantId,
        string $patientId,
        string $allergenName,
        ?string $ignoreAllergyId = null,
    ): bool;

    public function findInTenant(string $tenantId, string $patientId, string $allergyId): ?PatientAllergyData;

    /**
     * @return list<PatientAllergyData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;
}
