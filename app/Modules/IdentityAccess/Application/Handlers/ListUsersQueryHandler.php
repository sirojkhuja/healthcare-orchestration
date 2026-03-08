<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Queries\ListUsersQuery;
use App\Shared\Application\Contracts\TenantContext;

final class ListUsersQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ManagedUserRepository $managedUserRepository,
    ) {}

    /**
     * @return list<ManagedUserData>
     */
    public function handle(ListUsersQuery $query): array
    {
        return $this->managedUserRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $query->search,
            $query->status,
        );
    }
}
