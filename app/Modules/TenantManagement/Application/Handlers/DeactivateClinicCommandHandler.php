<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeactivateClinicCommand;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class DeactivateClinicCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(DeactivateClinicCommand $command): ClinicData
    {
        return $this->clinicAdministrationService->deactivateClinic($command->clinicId);
    }
}
