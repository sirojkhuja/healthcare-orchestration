<?php

namespace App\Modules\TenantManagement\Application\Contracts;

use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Data\TenantSettingsData;

interface TenantConfigurationRepository
{
    public function deleteForTenant(string $tenantId): void;

    public function limits(string $tenantId): TenantLimitsData;

    public function replaceLimits(string $tenantId, TenantLimitsData $limits): TenantLimitsData;

    public function replaceSettings(string $tenantId, TenantSettingsData $settings): TenantSettingsData;

    public function settings(string $tenantId): TenantSettingsData;
}
