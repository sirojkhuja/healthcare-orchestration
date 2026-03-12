<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditRetentionRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\AuditRetentionData;
use App\Modules\AuditCompliance\Application\Data\TenantAuditRetentionSettingData;
use App\Shared\Application\Contracts\TenantContext;

final class AuditRetentionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditRetentionRepository $auditRetentionRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function current(): AuditRetentionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $defaultRetentionDays = $this->defaultRetentionDays();
        $setting = $this->auditRetentionRepository->findForTenant($tenantId);

        return $this->viewFromSetting($tenantId, $defaultRetentionDays, $setting);
    }

    public function update(int $retentionDays): AuditRetentionData
    {
        $before = $this->current();
        $tenantId = $before->tenantId;
        $setting = $this->auditRetentionRepository->upsert($tenantId, $retentionDays);
        $after = $this->viewFromSetting($tenantId, $before->defaultRetentionDays, $setting);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'audit.retention_updated',
            objectType: 'audit_retention_policy',
            objectId: $tenantId,
            before: $before->toArray(),
            after: $after->toArray(),
            metadata: [
                'default_retention_days' => $before->defaultRetentionDays,
            ],
        ));

        return $after;
    }

    private function defaultRetentionDays(): int
    {
        /** @psalm-suppress MixedAssignment */
        $configured = config('medflow.audit.retention_days', 0);

        return is_numeric($configured) ? (int) $configured : 0;
    }

    private function viewFromSetting(
        string $tenantId,
        int $defaultRetentionDays,
        ?TenantAuditRetentionSettingData $setting,
    ): AuditRetentionData {
        $tenantRetentionDays = $setting?->retentionDays;
        $effective = $tenantRetentionDays ?? $defaultRetentionDays;

        return new AuditRetentionData(
            tenantId: $tenantId,
            defaultRetentionDays: $defaultRetentionDays,
            tenantRetentionDays: $tenantRetentionDays,
            effectiveRetentionDays: $effective,
            pruningEnabled: $effective > 0,
            updatedAt: $setting?->updatedAt,
        );
    }
}
