<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\TenantUsageData;
use App\Modules\TenantManagement\Application\Queries\GetTenantUsageQuery;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class GetTenantUsageQueryHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(GetTenantUsageQuery $query): TenantUsageData
    {
        return $this->tenantAdministrationService->usage($query->tenantId);
    }
}
