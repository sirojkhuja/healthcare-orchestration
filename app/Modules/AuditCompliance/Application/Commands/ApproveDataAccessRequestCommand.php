<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class ApproveDataAccessRequestCommand
{
    public function __construct(
        public string $requestId,
        public ?string $decisionNotes,
    ) {}
}
