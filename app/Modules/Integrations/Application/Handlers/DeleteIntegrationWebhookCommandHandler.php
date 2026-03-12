<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\DeleteIntegrationWebhookCommand;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use App\Modules\Integrations\Application\Services\IntegrationWebhookService;

final class DeleteIntegrationWebhookCommandHandler
{
    public function __construct(
        private readonly IntegrationWebhookService $integrationWebhookService,
    ) {}

    public function handle(DeleteIntegrationWebhookCommand $command): IntegrationWebhookData
    {
        return $this->integrationWebhookService->delete($command->integrationKey, $command->webhookId);
    }
}
