<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\RevokeIntegrationTokenCommand;
use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use App\Modules\Integrations\Application\Services\IntegrationTokenService;

final class RevokeIntegrationTokenCommandHandler
{
    public function __construct(
        private readonly IntegrationTokenService $integrationTokenService,
    ) {}

    public function handle(RevokeIntegrationTokenCommand $command): IntegrationTokenData
    {
        return $this->integrationTokenService->revoke($command->integrationKey, $command->tokenId);
    }
}
