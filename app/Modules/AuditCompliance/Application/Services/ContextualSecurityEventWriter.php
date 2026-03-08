<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Data\SecurityEventData;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ContextualSecurityEventWriter implements SecurityEventWriter
{
    public function __construct(
        private readonly SecurityEventRepository $securityEventRepository,
        private readonly AuditActorResolver $auditActorResolver,
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly TenantContext $tenantContext,
        private readonly Request $request,
    ) {}

    #[\Override]
    public function record(SecurityEventInput $input): SecurityEventData
    {
        $requestMetadata = $this->requestMetadataContext->current();
        $event = new SecurityEventData(
            eventId: (string) Str::uuid(),
            tenantId: $input->tenantId ?? $this->tenantContext->tenantId(),
            userId: $input->userId,
            eventType: $input->eventType,
            subjectType: $input->subjectType,
            subjectId: $input->subjectId,
            actor: $this->auditActorResolver->resolve(),
            requestId: $requestMetadata->requestId,
            correlationId: $requestMetadata->correlationId,
            ipAddress: $this->request->ip(),
            userAgent: $this->request->userAgent(),
            metadata: $input->metadata,
            occurredAt: CarbonImmutable::now(),
        );

        $this->securityEventRepository->append($event);

        return $event;
    }
}
