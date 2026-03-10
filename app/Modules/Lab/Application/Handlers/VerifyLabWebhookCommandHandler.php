<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\VerifyLabWebhookCommand;
use App\Modules\Lab\Application\Data\LabWebhookVerificationData;
use App\Modules\Lab\Application\Services\LabWebhookService;

final class VerifyLabWebhookCommandHandler
{
    public function __construct(
        private readonly LabWebhookService $labWebhookService,
    ) {}

    public function handle(VerifyLabWebhookCommand $command): LabWebhookVerificationData
    {
        return $this->labWebhookService->verify(
            providerKey: $command->providerKey,
            signature: $command->signature,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
