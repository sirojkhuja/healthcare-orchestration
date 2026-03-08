<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetTenantUsageQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
