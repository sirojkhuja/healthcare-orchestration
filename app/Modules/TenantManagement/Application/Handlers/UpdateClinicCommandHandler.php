<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateClinicCommand;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class UpdateClinicCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(UpdateClinicCommand $command): ClinicData
    {
        return $this->clinicAdministrationService->updateClinic($command->clinicId, $command->attributes);
    }
}
