<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Handlers\ListPermissionGroupsQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListPermissionsQueryHandler;
use App\Modules\IdentityAccess\Application\Queries\ListPermissionGroupsQuery;
use App\Modules\IdentityAccess\Application\Queries\ListPermissionsQuery;
use Illuminate\Http\JsonResponse;

final class PermissionCatalogController
{
    public function list(ListPermissionsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($permission) => $permission->toArray(),
                $handler->handle(new ListPermissionsQuery),
            ),
        ]);
    }

    public function groups(ListPermissionGroupsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($group) => $group->toArray(),
                $handler->handle(new ListPermissionGroupsQuery),
            ),
        ]);
    }
}
