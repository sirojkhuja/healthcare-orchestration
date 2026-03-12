<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Queries\MetricsQuery;
use App\Modules\Observability\Application\Services\MetricsService;

final class MetricsQueryHandler
{
    public function __construct(private readonly MetricsService $metricsService) {}

    public function handle(MetricsQuery $query): string
    {
        return $this->metricsService->render();
    }
}
