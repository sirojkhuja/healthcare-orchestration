<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Queries\GetReportQuery;
use App\Modules\Reporting\Application\Services\ReportService;

final class GetReportQueryHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function handle(GetReportQuery $query): ReportData
    {
        return $this->reportService->get($query->reportId);
    }
}
