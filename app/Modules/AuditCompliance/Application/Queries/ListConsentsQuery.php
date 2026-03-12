<?php

namespace App\Modules\AuditCompliance\Application\Queries;

use App\Modules\AuditCompliance\Application\Data\ConsentViewSearchCriteria;

final readonly class ListConsentsQuery
{
    public function __construct(
        public ConsentViewSearchCriteria $criteria,
    ) {}
}
