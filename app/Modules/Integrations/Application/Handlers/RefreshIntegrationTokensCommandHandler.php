<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\RefreshIntegrationTokensCommand;
use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use App\Modules\Integrations\Application\Services\IntegrationTokenService;

final class RefreshIntegrationTokensCommandHandler
{
    public function __construct(
        private readonly IntegrationTokenService $integrationTokenService,
    ) {}

    public function handle(RefreshIntegrationTokensCommand $command): IntegrationTokenData
    {
        return $this->integrationTokenService->refresh($command->integrationKey, $command->tokenId);
    }
}
