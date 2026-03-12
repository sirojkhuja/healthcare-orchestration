<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use App\Modules\Integrations\Application\Queries\ListIntegrationTokensQuery;
use App\Modules\Integrations\Application\Services\IntegrationTokenService;

final class ListIntegrationTokensQueryHandler
{
    public function __construct(
        private readonly IntegrationTokenService $integrationTokenService,
    ) {}

    /**
     * @return list<IntegrationTokenData>
     */
    public function handle(ListIntegrationTokensQuery $query): array
    {
        return $this->integrationTokenService->list($query->integrationKey);
    }
}
