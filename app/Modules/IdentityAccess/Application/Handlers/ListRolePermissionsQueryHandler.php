<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionDefinitionData;
use App\Modules\IdentityAccess\Application\Queries\ListRolePermissionsQuery;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListRolePermissionsQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
        private readonly PermissionCatalog $permissionCatalog,
    ) {}

    /**
     * @return list<PermissionDefinitionData>
     */
    public function handle(ListRolePermissionsQuery $query): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $role = $this->roleRepository->findInTenant($query->roleId, $tenantId);

        if ($role === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        return $this->permissionCatalog->definitions(
            $this->roleRepository->listPermissionNames($role->roleId, $tenantId),
        );
    }
}
