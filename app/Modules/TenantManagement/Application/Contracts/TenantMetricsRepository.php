<?php

namespace App\Modules\TenantManagement\Application\Contracts;

interface TenantMetricsRepository
{
    public function clinics(string $tenantId): int;

    public function monthlyNotifications(string $tenantId): int;

    public function patients(string $tenantId): int;

    public function providers(string $tenantId): int;

    public function storageGb(string $tenantId): float;

    public function users(string $tenantId): int;
}
