<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetClinicSettingsQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
