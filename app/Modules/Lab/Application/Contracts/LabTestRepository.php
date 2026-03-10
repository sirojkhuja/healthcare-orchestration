<?php

namespace App\Modules\Lab\Application\Contracts;

use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Data\LabTestListCriteria;

interface LabTestRepository
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     specimen_type: string,
     *     result_type: string,
     *     unit: ?string,
     *     reference_range: ?string,
     *     lab_provider_key: string,
     *     external_test_code: ?string,
     *     is_active: bool
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): LabTestData;

    public function delete(string $tenantId, string $testId): bool;

    public function findInTenant(string $tenantId, string $testId): ?LabTestData;

    /**
     * @return list<LabTestData>
     */
    public function listForTenant(string $tenantId, LabTestListCriteria $criteria): array;

    public function providerCodeExists(
        string $tenantId,
        string $labProviderKey,
        string $code,
        ?string $ignoreTestId = null,
    ): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $testId, array $updates): ?LabTestData;
}
