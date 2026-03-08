<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\UserPermissionSetData;
use App\Modules\IdentityAccess\Application\Queries\GetUserPermissionsQuery;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetUserPermissionsQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly PermissionProjectionRepository $permissionProjectionRepository,
        private readonly PermissionCatalog $permissionCatalog,
    ) {}

    public function handle(GetUserPermissionsQuery $query): UserPermissionSetData
    {
        if ($this->identityUserProvider->findById($query->userId) === null) {
            throw new NotFoundHttpException('The requested user does not exist.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $roles = $this->userRoleAssignmentRepository->listRolesForUser($query->userId, $tenantId);
        $projection = $this->permissionProjectionRepository->forUser($query->userId, $tenantId);

        return new UserPermissionSetData(
            userId: $query->userId,
            tenantId: $tenantId,
            roles: $roles,
            permissions: $this->permissionCatalog->definitions($projection->permissions),
        );
    }
}
