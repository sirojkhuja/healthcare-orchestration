<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Queries\ListAuditEventsQuery;
use App\Modules\AuditCompliance\Application\Services\AuditQueryService;

final class ListAuditEventsQueryHandler
{
    public function __construct(
        private readonly AuditQueryService $auditQueryService,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function handle(ListAuditEventsQuery $query): array
    {
        return $this->auditQueryService->list($query->criteria);
    }
}
