<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class SuspendTenantCommand
{
    public function __construct(
        public string $tenantId,
    ) {}
}
