<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\TenantAuditRetentionSettingData;

interface AuditRetentionRepository
{
    public function findForTenant(string $tenantId): ?TenantAuditRetentionSettingData;

    /**
     * @return list<TenantAuditRetentionSettingData>
     */
    public function all(): array;

    public function upsert(string $tenantId, int $retentionDays): TenantAuditRetentionSettingData;
}
