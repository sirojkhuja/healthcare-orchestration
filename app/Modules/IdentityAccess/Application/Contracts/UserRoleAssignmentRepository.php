<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\RoleData;

interface UserRoleAssignmentRepository
{
    /**
     * @return list<string>
     */
    public function assignedUserIdsForRole(string $roleId, string $tenantId): array;

    /**
     * @return list<RoleData>
     */
    public function listRolesForUser(string $userId, string $tenantId): array;

    /**
     * @param  list<string>  $roleIds
     */
    public function replaceRolesForUser(string $userId, string $tenantId, array $roleIds): void;
}
