<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Queries\GetTenantLimitsQuery;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class GetTenantLimitsQueryHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(GetTenantLimitsQuery $query): TenantLimitsData
    {
        return $this->tenantAdministrationService->limits($query->tenantId);
    }
}
