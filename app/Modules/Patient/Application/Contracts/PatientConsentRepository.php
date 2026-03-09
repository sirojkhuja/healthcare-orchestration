<?php

namespace App\Modules\Patient\Application\Contracts;

use App\Modules\Patient\Application\Data\PatientConsentData;
use Carbon\CarbonImmutable;

interface PatientConsentRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $patientId, array $attributes): PatientConsentData;

    public function findInTenant(string $tenantId, string $patientId, string $consentId): ?PatientConsentData;

    /**
     * @return list<PatientConsentData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;

    public function hasActiveConsentType(
        string $tenantId,
        string $patientId,
        string $consentType,
        CarbonImmutable $now,
    ): bool;

    public function revoke(
        string $tenantId,
        string $patientId,
        string $consentId,
        CarbonImmutable $revokedAt,
        ?string $reason,
    ): ?PatientConsentData;
}
