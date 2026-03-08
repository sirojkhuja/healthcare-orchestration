<?php

namespace App\Modules\TenantManagement\Application\Contracts;

use App\Modules\TenantManagement\Application\Data\TenantData;

interface TenantRepository
{
    public function create(string $name, ?string $contactEmail, ?string $contactPhone, string $status): TenantData;

    public function delete(string $tenantId): bool;

    public function find(string $tenantId): ?TenantData;

    public function findVisibleToUser(string $tenantId, string $userId): ?TenantData;

    /**
     * @return list<TenantData>
     */
    public function listVisibleToUser(string $userId, ?string $search = null, ?string $status = null): array;

    /**
     * @return list<string>
     */
    public function memberUserIds(string $tenantId): array;

    /**
     * @param  array<string, \Carbon\CarbonImmutable|string|null>  $attributes
     */
    public function update(string $tenantId, array $attributes): bool;
}
