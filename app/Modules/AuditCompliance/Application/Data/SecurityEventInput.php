<?php

namespace App\Modules\AuditCompliance\Application\Data;

final readonly class SecurityEventInput
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventType,
        public string $subjectType,
        public string $subjectId,
        public ?string $userId = null,
        public ?string $tenantId = null,
        public array $metadata = [],
    ) {}
}
