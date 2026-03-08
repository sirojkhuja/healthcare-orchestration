<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Modules\IdentityAccess\Application\Queries\ListUserRolesQuery;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListUserRolesQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
    ) {}

    /**
     * @return list<RoleData>
     */
    public function handle(ListUserRolesQuery $query): array
    {
        if ($this->identityUserProvider->findById($query->userId) === null) {
            throw new NotFoundHttpException('The requested user does not exist.');
        }

        return $this->userRoleAssignmentRepository->listRolesForUser(
            $query->userId,
            $this->tenantContext->requireTenantId(),
        );
    }
}
