<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class ActivateTenantCommand
{
    public function __construct(
        public string $tenantId,
    ) {}
}
