<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\LivenessData;
use App\Modules\Observability\Application\Queries\LivenessQuery;
use App\Modules\Observability\Application\Services\HealthService;

final class LivenessQueryHandler
{
    public function __construct(private readonly HealthService $healthService) {}

    public function handle(LivenessQuery $query): LivenessData
    {
        return $this->healthService->live();
    }
}
