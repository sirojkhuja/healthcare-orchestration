<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationCredentialViewData;
use App\Modules\Integrations\Application\Queries\GetIntegrationCredentialsQuery;
use App\Modules\Integrations\Application\Services\IntegrationCredentialService;

final class GetIntegrationCredentialsQueryHandler
{
    public function __construct(
        private readonly IntegrationCredentialService $integrationCredentialService,
    ) {}

    public function handle(GetIntegrationCredentialsQuery $query): IntegrationCredentialViewData
    {
        return $this->integrationCredentialService->get($query->integrationKey);
    }
}
