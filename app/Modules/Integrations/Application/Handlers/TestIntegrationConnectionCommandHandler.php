<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\TestIntegrationConnectionCommand;
use App\Modules\Integrations\Application\Data\IntegrationHealthData;
use App\Modules\Integrations\Application\Services\IntegrationDiagnosticsService;

final class TestIntegrationConnectionCommandHandler
{
    public function __construct(
        private readonly IntegrationDiagnosticsService $integrationDiagnosticsService,
    ) {}

    public function handle(TestIntegrationConnectionCommand $command): IntegrationHealthData
    {
        return $this->integrationDiagnosticsService->testConnection($command->integrationKey);
    }
}
