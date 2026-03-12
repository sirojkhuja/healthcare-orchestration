<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Commands\RunReportCommand;
use App\Modules\Reporting\Application\Data\ReportRunData;
use App\Modules\Reporting\Application\Services\ReportService;

final class RunReportCommandHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function handle(RunReportCommand $command): ReportRunData
    {
        return $this->reportService->run($command->reportId);
    }
}
