<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\ReceiveLabResultWebhookCommand;
use App\Modules\Lab\Application\Data\LabWebhookProcessResultData;
use App\Modules\Lab\Application\Services\LabWebhookService;

final class ReceiveLabResultWebhookCommandHandler
{
    public function __construct(
        private readonly LabWebhookService $labWebhookService,
    ) {}

    public function handle(ReceiveLabResultWebhookCommand $command): LabWebhookProcessResultData
    {
        return $this->labWebhookService->process(
            providerKey: $command->providerKey,
            signature: $command->signature,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
