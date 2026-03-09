<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListClinicHolidaysQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
