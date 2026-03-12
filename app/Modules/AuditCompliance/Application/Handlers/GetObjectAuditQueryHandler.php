<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Queries\GetObjectAuditQuery;
use App\Modules\AuditCompliance\Application\Services\AuditQueryService;

final class GetObjectAuditQueryHandler
{
    public function __construct(
        private readonly AuditQueryService $auditQueryService,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function handle(GetObjectAuditQuery $query): array
    {
        return $this->auditQueryService->objectHistory(
            $query->objectType,
            $query->objectId,
            $query->actionPrefix,
            $query->limit,
        );
    }
}
