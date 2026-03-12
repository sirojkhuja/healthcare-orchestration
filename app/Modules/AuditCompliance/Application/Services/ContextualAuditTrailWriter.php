<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ContextualAuditTrailWriter implements AuditTrailWriter
{
    public function __construct(
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditActorResolver $auditActorResolver,
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly TenantContext $tenantContext,
    ) {}

    #[\Override]
    public function record(AuditRecordInput $input): AuditEventData
    {
        $requestMetadata = $this->requestMetadataContext->current();
        $event = new AuditEventData(
            eventId: (string) Str::orderedUuid(),
            tenantId: $input->tenantId ?? $this->tenantContext->tenantId(),
            action: $input->action,
            objectType: $input->objectType,
            objectId: $input->objectId,
            actor: $this->auditActorResolver->resolve(),
            requestId: $requestMetadata->requestId,
            correlationId: $requestMetadata->correlationId,
            before: $input->before,
            after: $input->after,
            metadata: $input->metadata,
            occurredAt: CarbonImmutable::now(),
        );

        $this->auditEventRepository->append($event);

        return $event;
    }
}
