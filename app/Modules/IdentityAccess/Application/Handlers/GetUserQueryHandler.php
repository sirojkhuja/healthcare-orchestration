<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Queries\GetUserQuery;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetUserQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ManagedUserRepository $managedUserRepository,
    ) {}

    public function handle(GetUserQuery $query): ManagedUserData
    {
        $user = $this->managedUserRepository->findInTenant(
            $query->userId,
            $this->tenantContext->requireTenantId(),
        );

        if ($user === null) {
            throw new NotFoundHttpException('The requested user does not belong to the active tenant.');
        }

        return $user;
    }
}
