<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Commands\DeleteReportCommand;
use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Services\ReportService;

final class DeleteReportCommandHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function handle(DeleteReportCommand $command): ReportData
    {
        return $this->reportService->delete($command->reportId);
    }
}
