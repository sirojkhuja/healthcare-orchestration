<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Queries\GetTenantQuery;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class GetTenantQueryHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(GetTenantQuery $query): TenantData
    {
        return $this->tenantAdministrationService->get($query->tenantId);
    }
}
