<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditRetentionData;
use App\Modules\AuditCompliance\Application\Queries\GetAuditRetentionQuery;
use App\Modules\AuditCompliance\Application\Services\AuditRetentionService;

final class GetAuditRetentionQueryHandler
{
    public function __construct(
        private readonly AuditRetentionService $auditRetentionService,
    ) {}

    public function handle(GetAuditRetentionQuery $query): AuditRetentionData
    {
        return $this->auditRetentionService->current();
    }
}
