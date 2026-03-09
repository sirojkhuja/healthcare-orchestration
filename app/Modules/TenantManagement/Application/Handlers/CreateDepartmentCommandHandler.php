<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\CreateDepartmentCommand;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class CreateDepartmentCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(CreateDepartmentCommand $command): DepartmentData
    {
        return $this->clinicAdministrationService->createDepartment($command->clinicId, $command->attributes);
    }
}
