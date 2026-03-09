<?php

namespace App\Modules\Provider\Application\Contracts;

use App\Modules\Provider\Application\Data\ProviderSpecialtyData;
use App\Modules\Provider\Application\Data\SpecialtyData;

interface SpecialtyRepository
{
    public function create(string $tenantId, string $name, ?string $description): SpecialtyData;

    public function delete(string $tenantId, string $specialtyId): bool;

    public function findInTenant(string $tenantId, string $specialtyId): ?SpecialtyData;

    public function hasAssignments(string $tenantId, string $specialtyId): bool;

    /**
     * @return list<SpecialtyData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @return list<ProviderSpecialtyData>
     */
    public function listForProvider(string $tenantId, string $providerId): array;

    public function nameExists(string $tenantId, string $name, ?string $exceptSpecialtyId = null): bool;

    /**
     * @param  list<array{specialty_id: string, is_primary: bool}>  $assignments
     * @return list<ProviderSpecialtyData>
     */
    public function replaceProviderAssignments(string $tenantId, string $providerId, array $assignments): array;

    public function update(string $tenantId, string $specialtyId, string $name, ?string $description): ?SpecialtyData;
}
