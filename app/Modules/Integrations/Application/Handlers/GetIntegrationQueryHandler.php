<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationData;
use App\Modules\Integrations\Application\Queries\GetIntegrationQuery;
use App\Modules\Integrations\Application\Services\IntegrationRegistryService;

final class GetIntegrationQueryHandler
{
    public function __construct(
        private readonly IntegrationRegistryService $integrationRegistryService,
    ) {}

    public function handle(GetIntegrationQuery $query): IntegrationData
    {
        return $this->integrationRegistryService->show($query->integrationKey);
    }
}
