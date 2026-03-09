<?php

namespace App\Modules\Patient\Application\Contracts;

use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use Carbon\CarbonImmutable;

interface PatientRepository
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     middle_name: string|null,
     *     preferred_name: string|null,
     *     sex: string,
     *     birth_date: string,
     *     national_id: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     city_code: string|null,
     *     district_code: string|null,
     *     address_line_1: string|null,
     *     address_line_2: string|null,
     *     postal_code: string|null,
     *     notes: string|null
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): PatientData;

    public function findInTenant(string $tenantId, string $patientId, bool $withDeleted = false): ?PatientData;

    public function nationalIdExists(string $tenantId, string $nationalId, ?string $ignorePatientId = null): bool;

    /**
     * @return list<PatientData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @return list<PatientData>
     */
    public function search(string $tenantId, PatientSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $patientId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, string|null>  $updates
     */
    public function update(string $tenantId, string $patientId, array $updates): ?PatientData;
}
