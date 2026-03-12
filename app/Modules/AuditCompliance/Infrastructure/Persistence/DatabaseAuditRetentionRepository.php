<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\AuditRetentionRepository;
use App\Modules\AuditCompliance\Application\Data\TenantAuditRetentionSettingData;
use Carbon\CarbonImmutable;

final class DatabaseAuditRetentionRepository implements AuditRetentionRepository
{
    #[\Override]
    public function findForTenant(string $tenantId): ?TenantAuditRetentionSettingData
    {
        $record = AuditRetentionSettingRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', strtolower($tenantId))
            ->first();

        return $record instanceof AuditRetentionSettingRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function all(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, AuditRetentionSettingRecord> $records */
        $records = AuditRetentionSettingRecord::query()
            ->withoutGlobalScopes()
            ->orderBy('tenant_id')
            ->get();

        /** @var list<TenantAuditRetentionSettingData> $settings */
        $settings = array_values(array_map(
            fn (AuditRetentionSettingRecord $record) => $this->toData($record),
            $records->all(),
        ));

        return $settings;
    }

    #[\Override]
    public function upsert(string $tenantId, int $retentionDays): TenantAuditRetentionSettingData
    {
        /** @var AuditRetentionSettingRecord $record */
        $record = AuditRetentionSettingRecord::query()->updateOrCreate(
            ['tenant_id' => strtolower($tenantId)],
            ['retention_days' => $retentionDays],
        );

        return $this->toData($record);
    }

    private function toData(AuditRetentionSettingRecord $record): TenantAuditRetentionSettingData
    {
        return new TenantAuditRetentionSettingData(
            settingId: $this->stringValue($record->getAttribute('id')),
            tenantId: $this->stringValue($record->getAttribute('tenant_id')),
            retentionDays: $this->intValue($record->getAttribute('retention_days')),
            updatedAt: CarbonImmutable::parse($this->stringValue($record->getAttribute('updated_at'))),
        );
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
