<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\HealthReportData;
use App\Modules\Observability\Application\Queries\ReadinessQuery;
use App\Modules\Observability\Application\Services\HealthService;

final class ReadinessQueryHandler
{
    public function __construct(private readonly HealthService $healthService) {}

    public function handle(ReadinessQuery $query): HealthReportData
    {
        return $this->healthService->readiness();
    }
}
