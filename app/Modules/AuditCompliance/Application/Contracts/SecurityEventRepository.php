<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\SecurityEventData;

interface SecurityEventRepository
{
    public function append(SecurityEventData $event): void;
}
