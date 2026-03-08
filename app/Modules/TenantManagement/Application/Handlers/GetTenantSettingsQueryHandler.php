<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\TenantSettingsData;
use App\Modules\TenantManagement\Application\Queries\GetTenantSettingsQuery;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class GetTenantSettingsQueryHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(GetTenantSettingsQuery $query): TenantSettingsData
    {
        return $this->tenantAdministrationService->settings($query->tenantId);
    }
}
