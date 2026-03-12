<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportSearchCriteria;

interface ComplianceReportRepository
{
    public function append(ComplianceReportData $report): void;

    /**
     * @return list<ComplianceReportData>
     */
    public function listForTenant(string $tenantId, ComplianceReportSearchCriteria $criteria): array;
}
