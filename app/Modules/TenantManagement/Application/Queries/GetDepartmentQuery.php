<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetDepartmentQuery
{
    public function __construct(
        public string $clinicId,
        public string $departmentId,
    ) {}
}
