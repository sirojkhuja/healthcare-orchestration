<?php

namespace App\Modules\AuditCompliance\Application\Queries;

use App\Modules\AuditCompliance\Application\Data\DataAccessRequestSearchCriteria;

final readonly class ListDataAccessRequestsQuery
{
    public function __construct(
        public DataAccessRequestSearchCriteria $criteria,
    ) {}
}
