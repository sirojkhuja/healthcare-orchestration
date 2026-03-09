<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Queries\GetDepartmentQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class GetDepartmentQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(GetDepartmentQuery $query): DepartmentData
    {
        return $this->clinicAdministrationService->getDepartment($query->clinicId, $query->departmentId);
    }
}
