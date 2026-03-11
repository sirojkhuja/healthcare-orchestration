<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\VerifyUzumWebhookCommand;
use App\Modules\Integrations\Application\Data\UzumWebhookVerificationData;
use App\Modules\Integrations\Application\Services\UzumWebhookService;

final class VerifyUzumWebhookCommandHandler
{
    public function __construct(
        private readonly UzumWebhookService $uzumWebhookService,
    ) {}

    public function handle(VerifyUzumWebhookCommand $command): UzumWebhookVerificationData
    {
        return $this->uzumWebhookService->verify(
            operation: $command->operation,
            authorization: $command->authorization,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
