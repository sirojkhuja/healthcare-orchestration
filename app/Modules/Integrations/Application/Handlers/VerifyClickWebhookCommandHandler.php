<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\VerifyClickWebhookCommand;
use App\Modules\Integrations\Application\Data\ClickWebhookVerificationData;
use App\Modules\Integrations\Application\Services\ClickWebhookService;

final class VerifyClickWebhookCommandHandler
{
    public function __construct(
        private readonly ClickWebhookService $clickWebhookService,
    ) {}

    public function handle(VerifyClickWebhookCommand $command): ClickWebhookVerificationData
    {
        return $this->clickWebhookService->verify(
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
