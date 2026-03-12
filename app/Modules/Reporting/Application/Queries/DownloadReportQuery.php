<?php

namespace App\Modules\Reporting\Application\Queries;

final readonly class DownloadReportQuery
{
    public function __construct(
        public string $reportId,
    ) {}
}
