<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Data\ReportRunData;
use App\Modules\Reporting\Application\Queries\DownloadReportQuery;
use App\Modules\Reporting\Application\Services\ReportService;

final class DownloadReportQueryHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function handle(DownloadReportQuery $query): ReportRunData
    {
        return $this->reportService->downloadableRun($query->reportId);
    }
}
