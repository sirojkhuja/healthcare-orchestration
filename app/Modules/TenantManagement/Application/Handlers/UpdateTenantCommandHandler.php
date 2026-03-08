<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Services\TenantAdministrationService;

final class UpdateTenantCommandHandler
{
    public function __construct(
        private readonly TenantAdministrationService $tenantAdministrationService,
    ) {}

    public function handle(UpdateTenantCommand $command): TenantData
    {
        return $this->tenantAdministrationService->update(
            tenantId: $command->tenantId,
            nameProvided: $command->nameProvided,
            name: $command->name,
            contactEmailProvided: $command->contactEmailProvided,
            contactEmail: $command->contactEmail,
            contactPhoneProvided: $command->contactPhoneProvided,
            contactPhone: $command->contactPhone,
        );
    }
}
