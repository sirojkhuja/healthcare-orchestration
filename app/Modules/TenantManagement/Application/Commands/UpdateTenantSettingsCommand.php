<?php

namespace App\Modules\TenantManagement\Application\Commands;

use App\Modules\TenantManagement\Application\Data\TenantSettingsData;

final readonly class UpdateTenantSettingsCommand
{
    public function __construct(
        public string $tenantId,
        public TenantSettingsData $settings,
    ) {}
}
