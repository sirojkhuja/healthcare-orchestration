<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class GetTenantSettingsQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
