<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Data\PermissionDefinitionData;
use App\Modules\IdentityAccess\Application\Queries\ListPermissionsQuery;

final class ListPermissionsQueryHandler
{
    public function __construct(
        private readonly PermissionCatalog $permissionCatalog,
    ) {}

    /**
     * @return list<PermissionDefinitionData>
     */
    public function handle(ListPermissionsQuery $query): array
    {
        return $this->permissionCatalog->all();
    }
}
