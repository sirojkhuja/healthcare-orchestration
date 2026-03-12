<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\HealthReportData;
use App\Modules\Observability\Application\Queries\HealthQuery;
use App\Modules\Observability\Application\Services\HealthService;

final class HealthQueryHandler
{
    public function __construct(private readonly HealthService $healthService) {}

    public function handle(HealthQuery $query): HealthReportData
    {
        return $this->healthService->health();
    }
}
