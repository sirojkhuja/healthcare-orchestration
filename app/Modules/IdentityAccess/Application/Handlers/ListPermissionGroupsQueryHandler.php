<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Data\PermissionGroupData;
use App\Modules\IdentityAccess\Application\Queries\ListPermissionGroupsQuery;

final class ListPermissionGroupsQueryHandler
{
    public function __construct(
        private readonly PermissionCatalog $permissionCatalog,
    ) {}

    /**
     * @return list<PermissionGroupData>
     */
    public function handle(ListPermissionGroupsQuery $query): array
    {
        return $this->permissionCatalog->groups();
    }
}
