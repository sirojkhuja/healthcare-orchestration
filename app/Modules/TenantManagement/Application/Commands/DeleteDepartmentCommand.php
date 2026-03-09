<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeleteDepartmentCommand
{
    public function __construct(
        public string $clinicId,
        public string $departmentId,
    ) {}
}
