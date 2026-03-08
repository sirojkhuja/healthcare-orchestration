<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AuditEventData
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventId,
        public ?string $tenantId,
        public string $action,
        public string $objectType,
        public string $objectId,
        public AuditActor $actor,
        public string $requestId,
        public string $correlationId,
        public array $before,
        public array $after,
        public array $metadata,
        public CarbonImmutable $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantId,
            'action' => $this->action,
            'object_type' => $this->objectType,
            'object_id' => $this->objectId,
            'actor' => $this->actor->toArray(),
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'before' => $this->before,
            'after' => $this->after,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
