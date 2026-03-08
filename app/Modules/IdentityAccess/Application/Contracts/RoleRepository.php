<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\RoleData;

interface RoleRepository
{
    public function create(string $tenantId, string $name, ?string $description): RoleData;

    public function deleteInTenant(string $roleId, string $tenantId): bool;

    public function findInTenant(string $roleId, string $tenantId): ?RoleData;

    /**
     * @return list<string>
     */
    public function listPermissionNames(string $roleId, string $tenantId): array;

    /**
     * @return list<RoleData>
     */
    public function listForTenant(string $tenantId): array;

    public function nameExists(string $tenantId, string $name, ?string $ignoreRoleId = null): bool;

    /**
     * @param  list<string>  $permissionNames
     */
    public function replacePermissions(string $roleId, string $tenantId, array $permissionNames): void;

    public function updateInTenant(string $roleId, string $tenantId, string $name, ?string $description): ?RoleData;
}
