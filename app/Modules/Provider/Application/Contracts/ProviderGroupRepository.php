<?php

namespace App\Modules\Provider\Application\Contracts;

use App\Modules\Provider\Application\Data\ProviderGroupData;

interface ProviderGroupRepository
{
    public function create(string $tenantId, string $name, ?string $description, ?string $clinicId): ProviderGroupData;

    public function findInTenant(string $tenantId, string $groupId): ?ProviderGroupData;

    /**
     * @return list<ProviderGroupData>
     */
    public function listForTenant(string $tenantId): array;

    public function nameExists(string $tenantId, string $name, ?string $exceptGroupId = null): bool;

    /**
     * @param  list<string>  $providerIds
     */
    public function replaceMembers(string $tenantId, string $groupId, array $providerIds): ProviderGroupData;
}
