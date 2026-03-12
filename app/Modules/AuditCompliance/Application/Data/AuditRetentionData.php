<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AuditRetentionData
{
    public function __construct(
        public string $tenantId,
        public int $defaultRetentionDays,
        public ?int $tenantRetentionDays,
        public int $effectiveRetentionDays,
        public bool $pruningEnabled,
        public ?CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'default_retention_days' => $this->defaultRetentionDays,
            'tenant_retention_days' => $this->tenantRetentionDays,
            'effective_retention_days' => $this->effectiveRetentionDays,
            'pruning_enabled' => $this->pruningEnabled,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
