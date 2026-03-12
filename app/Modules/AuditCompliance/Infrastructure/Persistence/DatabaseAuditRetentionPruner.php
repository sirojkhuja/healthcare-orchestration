<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\AuditRetentionPruner;
use App\Modules\AuditCompliance\Application\Contracts\AuditRetentionRepository;
use Carbon\CarbonImmutable;

final class DatabaseAuditRetentionPruner implements AuditRetentionPruner
{
    public function __construct(
        private readonly AuditRetentionRepository $auditRetentionRepository,
    ) {}

    #[\Override]
    public function prune(int $defaultRetentionDays, CarbonImmutable $now): int
    {
        $deleted = 0;
        $overrides = $this->auditRetentionRepository->all();
        $handledTenantIds = [];

        foreach ($overrides as $override) {
            $handledTenantIds[] = $override->tenantId;

            if ($override->retentionDays === 0) {
                continue;
            }

            $deleted += $this->deletedCount(AuditEventRecord::query()
                ->where('tenant_id', $override->tenantId)
                ->where('occurred_at', '<', $now->subDays($override->retentionDays))
                ->delete());
        }

        if ($defaultRetentionDays > 0) {
            $cutoff = $now->subDays($defaultRetentionDays);

            $deleted += $this->deletedCount(AuditEventRecord::query()
                ->whereNull('tenant_id')
                ->where('occurred_at', '<', $cutoff)
                ->delete());

            $tenantQuery = AuditEventRecord::query()
                ->whereNotNull('tenant_id')
                ->where('occurred_at', '<', $cutoff);

            if ($handledTenantIds !== []) {
                $tenantQuery->whereNotIn('tenant_id', $handledTenantIds);
            }

            $deleted += $this->deletedCount($tenantQuery->delete());
        }

        return $deleted;
    }

    private function deletedCount(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
