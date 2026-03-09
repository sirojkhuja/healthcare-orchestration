<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateDepartmentCommand;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class UpdateDepartmentCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(UpdateDepartmentCommand $command): DepartmentData
    {
        return $this->clinicAdministrationService->updateDepartment(
            $command->clinicId,
            $command->departmentId,
            $command->attributes,
        );
    }
}
