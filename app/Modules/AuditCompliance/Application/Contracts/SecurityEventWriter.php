<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\SecurityEventData;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;

interface SecurityEventWriter
{
    public function record(SecurityEventInput $input): SecurityEventData;
}
