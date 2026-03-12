<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationHealthData;
use App\Modules\Integrations\Application\Queries\IntegrationHealthQuery;
use App\Modules\Integrations\Application\Services\IntegrationDiagnosticsService;

final class IntegrationHealthQueryHandler
{
    public function __construct(
        private readonly IntegrationDiagnosticsService $integrationDiagnosticsService,
    ) {}

    public function handle(IntegrationHealthQuery $query): IntegrationHealthData
    {
        return $this->integrationDiagnosticsService->health($query->integrationKey);
    }
}
