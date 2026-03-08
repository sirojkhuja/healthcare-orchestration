<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetTenantQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
