<?php

namespace App\Modules\AuditCompliance\Application\Commands;

final readonly class UpdateAuditRetentionCommand
{
    public function __construct(
        public int $retentionDays,
    ) {}
}
