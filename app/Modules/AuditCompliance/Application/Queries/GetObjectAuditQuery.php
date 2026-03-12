<?php

namespace App\Modules\AuditCompliance\Application\Queries;

final readonly class GetObjectAuditQuery
{
    public function __construct(
        public string $objectType,
        public string $objectId,
        public ?string $actionPrefix = null,
        public int $limit = 50,
    ) {}
}
