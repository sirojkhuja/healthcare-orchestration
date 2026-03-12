<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Queries\GetAuditEventQuery;
use App\Modules\AuditCompliance\Application\Services\AuditQueryService;

final class GetAuditEventQueryHandler
{
    public function __construct(
        private readonly AuditQueryService $auditQueryService,
    ) {}

    public function handle(GetAuditEventQuery $query): AuditEventData
    {
        return $this->auditQueryService->show($query->eventId);
    }
}
