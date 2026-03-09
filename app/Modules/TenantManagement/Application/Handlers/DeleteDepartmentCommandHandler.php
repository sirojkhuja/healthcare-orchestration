<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeleteDepartmentCommand;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class DeleteDepartmentCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(DeleteDepartmentCommand $command): DepartmentData
    {
        return $this->clinicAdministrationService->deleteDepartment($command->clinicId, $command->departmentId);
    }
}
