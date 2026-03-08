<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\PermissionDefinitionData;
use App\Modules\IdentityAccess\Application\Data\PermissionGroupData;

interface PermissionCatalog
{
    /**
     * @return list<PermissionDefinitionData>
     */
    public function all(): array;

    /**
     * @param  list<string>  $permissionNames
     * @return list<PermissionDefinitionData>
     */
    public function definitions(array $permissionNames): array;

    public function exists(string $permissionName): bool;

    /**
     * @return list<PermissionGroupData>
     */
    public function groups(): array;
}
