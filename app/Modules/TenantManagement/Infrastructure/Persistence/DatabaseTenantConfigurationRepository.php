<?php

namespace App\Modules\TenantManagement\Infrastructure\Persistence;

use App\Modules\TenantManagement\Application\Contracts\TenantConfigurationRepository;
use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Data\TenantSettingsData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseTenantConfigurationRepository implements TenantConfigurationRepository
{
    #[\Override]
    public function deleteForTenant(string $tenantId): void
    {
        DB::table('tenant_limits')->where('tenant_id', $tenantId)->delete();
        DB::table('tenant_settings')->where('tenant_id', $tenantId)->delete();
    }

    #[\Override]
    public function limits(string $tenantId): TenantLimitsData
    {
        $row = DB::table('tenant_limits')->where('tenant_id', $tenantId)->first();

        if ($row === null) {
            return new TenantLimitsData;
        }

        return new TenantLimitsData(
            users: $this->nullableInt($row->users ?? null),
            clinics: $this->nullableInt($row->clinics ?? null),
            providers: $this->nullableInt($row->providers ?? null),
            patients: $this->nullableInt($row->patients ?? null),
            storageGb: $this->nullableFloat($row->storage_gb ?? null),
            monthlyNotifications: $this->nullableInt($row->monthly_notifications ?? null),
            updatedAt: $this->nullableTimestamp($row->updated_at ?? null),
        );
    }

    #[\Override]
    public function replaceLimits(string $tenantId, TenantLimitsData $limits): TenantLimitsData
    {
        $now = CarbonImmutable::now();
        $payload = [
            'users' => $limits->users,
            'clinics' => $limits->clinics,
            'providers' => $limits->providers,
            'patients' => $limits->patients,
            'storage_gb' => $limits->storageGb,
            'monthly_notifications' => $limits->monthlyNotifications,
            'updated_at' => $now,
        ];

        if (DB::table('tenant_limits')->where('tenant_id', $tenantId)->exists()) {
            DB::table('tenant_limits')->where('tenant_id', $tenantId)->update($payload);
        } else {
            DB::table('tenant_limits')->insert($payload + [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'created_at' => $now,
            ]);
        }

        return $this->limits($tenantId);
    }

    #[\Override]
    public function replaceSettings(string $tenantId, TenantSettingsData $settings): TenantSettingsData
    {
        $now = CarbonImmutable::now();
        $payload = [
            'locale' => $settings->locale,
            'timezone' => $settings->timezone,
            'currency' => $settings->currency,
            'updated_at' => $now,
        ];

        if (DB::table('tenant_settings')->where('tenant_id', $tenantId)->exists()) {
            DB::table('tenant_settings')->where('tenant_id', $tenantId)->update($payload);
        } else {
            DB::table('tenant_settings')->insert($payload + [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'created_at' => $now,
            ]);
        }

        return $this->settings($tenantId);
    }

    #[\Override]
    public function settings(string $tenantId): TenantSettingsData
    {
        $row = DB::table('tenant_settings')->where('tenant_id', $tenantId)->first();

        if ($row === null) {
            return new TenantSettingsData;
        }

        return new TenantSettingsData(
            locale: $this->nullableString($row->locale ?? null),
            timezone: $this->nullableString($row->timezone ?? null),
            currency: $this->nullableString($row->currency ?? null),
            updatedAt: $this->nullableTimestamp($row->updated_at ?? null),
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableTimestamp(mixed $value): ?CarbonImmutable
    {
        return is_string($value) || $value instanceof \DateTimeInterface ? CarbonImmutable::parse($value) : null;
    }
}
