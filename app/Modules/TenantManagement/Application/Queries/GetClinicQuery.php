<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetClinicQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
