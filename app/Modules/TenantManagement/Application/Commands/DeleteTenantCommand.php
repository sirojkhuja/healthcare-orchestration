<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeleteTenantCommand
{
    public function __construct(
        public string $tenantId,
    ) {}
}
