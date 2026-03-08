<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;

interface AuditTrailWriter
{
    public function record(AuditRecordInput $input): AuditEventData;
}
