<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class DeleteIntegrationWebhookCommand
{
    public function __construct(
        public string $integrationKey,
        public string $webhookId,
    ) {}
}
