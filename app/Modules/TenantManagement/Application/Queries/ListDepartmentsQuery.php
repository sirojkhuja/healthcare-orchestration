<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListDepartmentsQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
