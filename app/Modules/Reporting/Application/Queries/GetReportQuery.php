<?php

namespace App\Modules\Reporting\Application\Queries;

final readonly class GetReportQuery
{
    public function __construct(
        public string $reportId,
    ) {}
}
