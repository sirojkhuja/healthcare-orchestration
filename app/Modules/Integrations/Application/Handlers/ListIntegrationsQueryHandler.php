<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationData;
use App\Modules\Integrations\Application\Queries\ListIntegrationsQuery;
use App\Modules\Integrations\Application\Services\IntegrationRegistryService;

final class ListIntegrationsQueryHandler
{
    public function __construct(
        private readonly IntegrationRegistryService $integrationRegistryService,
    ) {}

    /**
     * @return list<IntegrationData>
     */
    public function handle(ListIntegrationsQuery $query): array
    {
        return $this->integrationRegistryService->list($query->category, $query->enabled);
    }
}
