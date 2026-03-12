<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use Carbon\CarbonImmutable;

interface AuditRetentionPruner
{
    public function prune(int $defaultRetentionDays, CarbonImmutable $now): int;
}
