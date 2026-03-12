<?php

namespace App\Modules\Reporting\Application\Queries;

use App\Modules\Reporting\Application\Data\ReportSearchCriteria;

final readonly class ListReportsQuery
{
    public function __construct(
        public ReportSearchCriteria $criteria,
    ) {}
}
