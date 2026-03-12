<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Queries\ListReportsQuery;
use App\Modules\Reporting\Application\Services\ReportService;

final class ListReportsQueryHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * @return list<ReportData>
     */
    public function handle(ListReportsQuery $query): array
    {
        return $this->reportService->list($query->criteria);
    }
}
