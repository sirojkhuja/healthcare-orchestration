<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\VersionData;
use App\Modules\Observability\Application\Queries\VersionQuery;
use App\Modules\Observability\Application\Services\HealthService;

final class VersionQueryHandler
{
    public function __construct(private readonly HealthService $healthService) {}

    public function handle(VersionQuery $query): VersionData
    {
        return $this->healthService->version();
    }
}
