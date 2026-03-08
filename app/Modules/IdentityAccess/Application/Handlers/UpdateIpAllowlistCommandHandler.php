<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\UpdateIpAllowlistCommand;
use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Data\TenantIpAllowlistEntryData;
use App\Shared\Application\Contracts\TenantContext;

final class UpdateIpAllowlistCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantIpAllowlistRepository $tenantIpAllowlistRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<TenantIpAllowlistEntryData>
     */
    public function handle(UpdateIpAllowlistCommand $command): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->tenantIpAllowlistRepository->listForTenant($tenantId);
        $after = $this->tenantIpAllowlistRepository->replaceForTenant($tenantId, $command->entries);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'security.ip_allowlist_updated',
            objectType: 'tenant',
            objectId: $tenantId,
            before: [
                'entries' => array_map(static fn (TenantIpAllowlistEntryData $entry): array => $entry->toArray(), $before),
            ],
            after: [
                'entries' => array_map(static fn (TenantIpAllowlistEntryData $entry): array => $entry->toArray(), $after),
            ],
            metadata: [
                'entry_count' => count($after),
            ],
        ));

        return $after;
    }
}
