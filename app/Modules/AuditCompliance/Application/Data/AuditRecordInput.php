<?php

namespace App\Modules\AuditCompliance\Application\Data;

final readonly class AuditRecordInput
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $action,
        public string $objectType,
        public string $objectId,
        public array $before = [],
        public array $after = [],
        public array $metadata = [],
        public ?string $tenantId = null,
    ) {}
}
