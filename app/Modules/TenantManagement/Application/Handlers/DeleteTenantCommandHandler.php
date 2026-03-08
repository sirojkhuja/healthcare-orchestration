<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeleteTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class DeleteTenantCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(DeleteTenantCommand $command): TenantData
    {
        return $this->tenantAdministrationService->delete($command->tenantId);
    }
}
