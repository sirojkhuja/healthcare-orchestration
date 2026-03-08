<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\SuspendTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class SuspendTenantCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(SuspendTenantCommand $command): TenantData
    {
        return $this->tenantAdministrationService->suspend($command->tenantId);
    }
}
