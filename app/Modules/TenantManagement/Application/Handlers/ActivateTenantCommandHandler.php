<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\ActivateTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class ActivateTenantCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(ActivateTenantCommand $command): TenantData
    {
        return $this->tenantAdministrationService->activate($command->tenantId);
    }
}
