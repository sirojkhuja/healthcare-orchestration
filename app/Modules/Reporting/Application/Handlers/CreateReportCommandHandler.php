<?php

namespace App\Modules\Reporting\Application\Handlers;

use App\Modules\Reporting\Application\Commands\CreateReportCommand;
use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Services\ReportService;

final class CreateReportCommandHandler
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function handle(CreateReportCommand $command): ReportData
    {
        return $this->reportService->create($command->attributes);
    }
}
