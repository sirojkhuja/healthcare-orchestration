<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\CreateIntegrationWebhookCommand;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use App\Modules\Integrations\Application\Services\IntegrationWebhookService;

final class CreateIntegrationWebhookCommandHandler
{
    public function __construct(
        private readonly IntegrationWebhookService $integrationWebhookService,
    ) {}

    public function handle(CreateIntegrationWebhookCommand $command): IntegrationWebhookData
    {
        return $this->integrationWebhookService->create($command->integrationKey, $command->attributes);
    }
}
