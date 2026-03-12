<?php

namespace App\Modules\AuditCompliance\Application\Queries;

use App\Modules\AuditCompliance\Application\Data\ComplianceReportSearchCriteria;

final readonly class ListComplianceReportsQuery
{
    public function __construct(
        public ComplianceReportSearchCriteria $criteria,
    ) {}
}
