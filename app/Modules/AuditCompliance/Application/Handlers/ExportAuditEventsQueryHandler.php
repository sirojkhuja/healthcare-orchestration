<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\AuditExportData;
use App\Modules\AuditCompliance\Application\Queries\ExportAuditEventsQuery;
use App\Modules\AuditCompliance\Application\Services\AuditQueryService;

final class ExportAuditEventsQueryHandler
{
    public function __construct(
        private readonly AuditQueryService $auditQueryService,
    ) {}

    public function handle(ExportAuditEventsQuery $query): AuditExportData
    {
        return $this->auditQueryService->export($query->criteria, $query->format);
    }
}
