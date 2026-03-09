<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\CreateClinicCommand;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class CreateClinicCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(CreateClinicCommand $command): ClinicData
    {
        return $this->clinicAdministrationService->createClinic($command->attributes);
    }
}
