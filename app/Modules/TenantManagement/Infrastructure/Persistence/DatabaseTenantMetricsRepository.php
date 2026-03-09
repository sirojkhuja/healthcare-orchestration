<?php

namespace App\Modules\TenantManagement\Infrastructure\Persistence;

use App\Modules\TenantManagement\Application\Contracts\TenantMetricsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseTenantMetricsRepository implements TenantMetricsRepository
{
    #[\Override]
    public function clinics(string $tenantId): int
    {
        return $this->tenantCount('clinics', $tenantId);
    }

    #[\Override]
    public function monthlyNotifications(string $tenantId): int
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'tenant_id')) {
            return 0;
        }

        if (! Schema::hasColumn('notifications', 'created_at')) {
            return 0;
        }

        return DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->count();
    }

    #[\Override]
    public function patients(string $tenantId): int
    {
        return $this->tenantCount('patients', $tenantId);
    }

    #[\Override]
    public function providers(string $tenantId): int
    {
        return $this->tenantCount('providers', $tenantId);
    }

    #[\Override]
    public function storageGb(string $tenantId): float
    {
        return 0.0;
    }

    #[\Override]
    public function users(string $tenantId): int
    {
        return DB::table('tenant_user_memberships')
            ->where('tenant_id', $tenantId)
            ->count();
    }

    private function tenantCount(string $table, string $tenantId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        $query = DB::table($table)
            ->where('tenant_id', $tenantId);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }
}
