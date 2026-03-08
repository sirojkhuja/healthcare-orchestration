<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetTenantLimitsQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
