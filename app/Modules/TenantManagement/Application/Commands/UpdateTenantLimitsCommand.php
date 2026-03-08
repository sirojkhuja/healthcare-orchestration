<?php

namespace App\Modules\TenantManagement\Application\Commands;

use App\Modules\TenantManagement\Application\Data\TenantLimitsData;

final readonly class UpdateTenantLimitsCommand
{
    public function __construct(
        public string $tenantId,
        public TenantLimitsData $limits,
    ) {}
}
