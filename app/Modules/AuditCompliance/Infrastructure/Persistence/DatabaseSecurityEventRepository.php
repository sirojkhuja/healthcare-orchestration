<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\SecurityEventRepository;
use App\Modules\AuditCompliance\Application\Data\SecurityEventData;

final class DatabaseSecurityEventRepository implements SecurityEventRepository
{
    #[\Override]
    public function append(SecurityEventData $event): void
    {
        SecurityEventRecord::query()->create([
            'id' => $event->eventId,
            'tenant_id' => $event->tenantId,
            'user_id' => $event->userId,
            'event_type' => $event->eventType,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'actor_type' => $event->actor->type,
            'actor_id' => $event->actor->id,
            'actor_name' => $event->actor->name,
            'request_id' => $event->requestId,
            'correlation_id' => $event->correlationId,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurredAt,
            'created_at' => $event->occurredAt,
        ]);
    }
}
