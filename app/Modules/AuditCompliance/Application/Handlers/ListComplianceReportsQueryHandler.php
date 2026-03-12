<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Queries\ListComplianceReportsQuery;
use App\Modules\AuditCompliance\Application\Services\PiiGovernanceService;

final class ListComplianceReportsQueryHandler
{
    public function __construct(
        private readonly PiiGovernanceService $piiGovernanceService,
    ) {}

    /**
     * @return list<ComplianceReportData>
     */
    public function handle(ListComplianceReportsQuery $query): array
    {
        return $this->piiGovernanceService->reports($query->criteria);
    }
}
