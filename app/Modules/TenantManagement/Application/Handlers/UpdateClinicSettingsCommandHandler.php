<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateClinicSettingsCommand;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class UpdateClinicSettingsCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(UpdateClinicSettingsCommand $command): ClinicSettingsData
    {
        return $this->clinicAdministrationService->updateClinicSettings($command->clinicId, $command->settings);
    }
}
