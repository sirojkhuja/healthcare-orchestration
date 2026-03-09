<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetClinicWorkHoursQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
