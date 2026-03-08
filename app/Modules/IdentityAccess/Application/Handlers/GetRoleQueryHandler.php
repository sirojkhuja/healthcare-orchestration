<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Modules\IdentityAccess\Application\Queries\GetRoleQuery;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetRoleQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
    ) {}

    public function handle(GetRoleQuery $query): RoleData
    {
        $role = $this->roleRepository->findInTenant(
            $query->roleId,
            $this->tenantContext->requireTenantId(),
        );

        if ($role === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        return $role;
    }
}
