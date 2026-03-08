<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\AuditActor;

interface AuditActorResolver
{
    public function resolve(): AuditActor;
}
