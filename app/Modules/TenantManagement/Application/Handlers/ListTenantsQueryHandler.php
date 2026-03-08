<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Queries\ListTenantsQuery;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class ListTenantsQueryHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    /**
     * @return list<TenantData>
     */
    public function handle(ListTenantsQuery $query): array
    {
        return $this->tenantAdministrationService->list(
            $query->search,
            $query->status,
        );
    }
}
