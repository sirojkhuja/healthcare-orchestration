<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class DenyDataAccessRequestCommand
{
    public function __construct(
        public string $requestId,
        public string $reason,
        public ?string $decisionNotes,
    ) {}
}
