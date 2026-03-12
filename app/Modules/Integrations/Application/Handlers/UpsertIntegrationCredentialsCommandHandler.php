<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\UpsertIntegrationCredentialsCommand;
use App\Modules\Integrations\Application\Data\IntegrationCredentialViewData;
use App\Modules\Integrations\Application\Services\IntegrationCredentialService;

final class UpsertIntegrationCredentialsCommandHandler
{
    public function __construct(
        private readonly IntegrationCredentialService $integrationCredentialService,
    ) {}

    public function handle(UpsertIntegrationCredentialsCommand $command): IntegrationCredentialViewData
    {
        return $this->integrationCredentialService->upsert($command->integrationKey, $command->attributes);
    }
}
