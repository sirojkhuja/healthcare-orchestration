<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class SecurityEventData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventId,
        public ?string $tenantId,
        public ?string $userId,
        public string $eventType,
        public string $subjectType,
        public string $subjectId,
        public AuditActor $actor,
        public string $requestId,
        public string $correlationId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public array $metadata,
        public CarbonImmutable $occurredAt,
    ) {}
}
