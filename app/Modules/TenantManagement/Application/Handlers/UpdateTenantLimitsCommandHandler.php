<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateTenantLimitsCommand;
use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class UpdateTenantLimitsCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(UpdateTenantLimitsCommand $command): TenantLimitsData
    {
        return $this->tenantAdministrationService->updateLimits(
            $command->tenantId,
            $command->limits,
        );
    }
}
