<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListDepartmentsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListDepartmentsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\DepartmentData>
     */
    public function handle(ListDepartmentsQuery $query): array
    {
        return $this->clinicAdministrationService->listDepartments($query->clinicId);
    }
}
