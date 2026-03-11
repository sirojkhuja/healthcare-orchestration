<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\VerifyPaymeWebhookCommand;
use App\Modules\Integrations\Application\Data\PaymeWebhookVerificationData;
use App\Modules\Integrations\Application\Services\PaymeWebhookService;

final class VerifyPaymeWebhookCommandHandler
{
    public function __construct(
        private readonly PaymeWebhookService $paymeWebhookService,
    ) {}

    public function handle(VerifyPaymeWebhookCommand $command): PaymeWebhookVerificationData
    {
        return $this->paymeWebhookService->verify(
            authorization: $command->authorization,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
