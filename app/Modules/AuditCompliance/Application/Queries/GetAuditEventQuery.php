<?php

namespace App\Modules\AuditCompliance\Application\Queries;

final readonly class GetAuditEventQuery
{
    public function __construct(
        public string $eventId,
    ) {}
}
