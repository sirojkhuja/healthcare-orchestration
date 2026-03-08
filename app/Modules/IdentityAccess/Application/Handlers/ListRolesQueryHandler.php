<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Modules\IdentityAccess\Application\Queries\ListRolesQuery;
use App\Shared\Application\Contracts\TenantContext;

final class ListRolesQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
    ) {}

    /**
     * @return list<RoleData>
     */
    public function handle(ListRolesQuery $query): array
    {
        return $this->roleRepository->listForTenant($this->tenantContext->requireTenantId());
    }
}
