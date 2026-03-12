<?php

namespace App\Modules\AuditCompliance\Application\Queries;

use App\Modules\AuditCompliance\Application\Data\AuditEventSearchCriteria;

final readonly class ExportAuditEventsQuery
{
    public function __construct(
        public AuditEventSearchCriteria $criteria,
        public string $format = 'csv',
    ) {}
}
