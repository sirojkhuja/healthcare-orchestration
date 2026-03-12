<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\EnableIntegrationCommand;
use App\Modules\Integrations\Application\Data\IntegrationData;
use App\Modules\Integrations\Application\Services\IntegrationRegistryService;

final class EnableIntegrationCommandHandler
{
    public function __construct(
        private readonly IntegrationRegistryService $integrationRegistryService,
    ) {}

    public function handle(EnableIntegrationCommand $command): IntegrationData
    {
        return $this->integrationRegistryService->enable($command->integrationKey);
    }
}
