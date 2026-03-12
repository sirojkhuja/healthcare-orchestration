<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TenantAuditRetentionSettingData
{
    public function __construct(
        public string $settingId,
        public string $tenantId,
        public int $retentionDays,
        public CarbonImmutable $updatedAt,
    ) {}
}
