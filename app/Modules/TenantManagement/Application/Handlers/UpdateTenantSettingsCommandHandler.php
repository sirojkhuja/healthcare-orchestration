<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateTenantSettingsCommand;
use App\Modules\TenantManagement\Application\Data\TenantSettingsData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class UpdateTenantSettingsCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(UpdateTenantSettingsCommand $command): TenantSettingsData
    {
        return $this->tenantAdministrationService->updateSettings(
            $command->tenantId,
            $command->settings,
        );
    }
}
