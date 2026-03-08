<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\TenantIpAllowlistEntryData;

interface TenantIpAllowlistRepository
{
    public function allows(string $tenantId, ?string $ipAddress): bool;

    /**
     * @return list<TenantIpAllowlistEntryData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @param  list<array{cidr: string, label: string|null}>  $entries
     * @return list<TenantIpAllowlistEntryData>
     */
    public function replaceForTenant(string $tenantId, array $entries): array;
}
