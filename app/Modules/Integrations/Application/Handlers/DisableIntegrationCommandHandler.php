<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\DisableIntegrationCommand;
use App\Modules\Integrations\Application\Data\IntegrationData;
use App\Modules\Integrations\Application\Services\IntegrationRegistryService;

final class DisableIntegrationCommandHandler
{
    public function __construct(
        private readonly IntegrationRegistryService $integrationRegistryService,
    ) {}

    public function handle(DisableIntegrationCommand $command): IntegrationData
    {
        return $this->integrationRegistryService->disable($command->integrationKey);
    }
}
