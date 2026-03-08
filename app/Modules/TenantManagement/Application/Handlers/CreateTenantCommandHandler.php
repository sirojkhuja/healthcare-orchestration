<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\CreateTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class CreateTenantCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(CreateTenantCommand $command): TenantData
    {
        return $this->tenantAdministrationService->create(
            $command->name,
            $command->contactEmail,
            $command->contactPhone,
        );
    }
}
