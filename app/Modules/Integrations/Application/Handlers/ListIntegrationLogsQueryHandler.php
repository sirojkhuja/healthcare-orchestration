<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationLogData;
use App\Modules\Integrations\Application\Queries\ListIntegrationLogsQuery;
use App\Modules\Integrations\Application\Services\IntegrationDiagnosticsService;

final class ListIntegrationLogsQueryHandler
{
    public function __construct(
        private readonly IntegrationDiagnosticsService $integrationDiagnosticsService,
    ) {}

    /**
     * @return list<IntegrationLogData>
     */
    public function handle(ListIntegrationLogsQuery $query): array
    {
        return $this->integrationDiagnosticsService->listLogs(
            $query->integrationKey,
            $query->level,
            $query->event,
            $query->limit,
        );
    }
}
