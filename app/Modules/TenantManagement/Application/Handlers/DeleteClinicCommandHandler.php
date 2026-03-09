<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeleteClinicCommand;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class DeleteClinicCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(DeleteClinicCommand $command): ClinicData
    {
        return $this->clinicAdministrationService->deleteClinic($command->clinicId);
    }
}
