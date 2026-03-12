<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\DeleteIntegrationCredentialsCommand;
use App\Modules\Integrations\Application\Data\IntegrationCredentialViewData;
use App\Modules\Integrations\Application\Services\IntegrationCredentialService;

final class DeleteIntegrationCredentialsCommandHandler
{
    public function __construct(
        private readonly IntegrationCredentialService $integrationCredentialService,
    ) {}

    public function handle(DeleteIntegrationCredentialsCommand $command): IntegrationCredentialViewData
    {
        return $this->integrationCredentialService->delete($command->integrationKey);
    }
}
