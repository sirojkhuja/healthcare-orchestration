<?php

namespace App\Modules\Pharmacy\Application\Contracts;

use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;

interface MedicationRepository
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     generic_name: ?string,
     *     form: ?string,
     *     strength: ?string,
     *     description: ?string,
     *     is_active: bool
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): MedicationData;

    public function delete(string $tenantId, string $medicationId): bool;

    public function findByCode(string $tenantId, string $code): ?MedicationData;

    public function findInTenant(string $tenantId, string $medicationId): ?MedicationData;

    /**
     * @return list<MedicationData>
     */
    public function listForTenant(string $tenantId, MedicationListCriteria $criteria): array;

    public function codeExists(string $tenantId, string $code, ?string $ignoreMedicationId = null): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $medicationId, array $updates): ?MedicationData;
}
