<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\RotateWebhookSecretCommand;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use App\Modules\Integrations\Application\Services\IntegrationWebhookService;

final class RotateWebhookSecretCommandHandler
{
    public function __construct(
        private readonly IntegrationWebhookService $integrationWebhookService,
    ) {}

    public function handle(RotateWebhookSecretCommand $command): IntegrationWebhookData
    {
        return $this->integrationWebhookService->rotateSecret(
            $command->integrationKey,
            $command->webhookId,
            $command->attributes,
        );
    }
}
