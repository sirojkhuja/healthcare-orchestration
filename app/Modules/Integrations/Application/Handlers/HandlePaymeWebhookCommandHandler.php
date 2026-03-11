<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandlePaymeWebhookCommand;
use App\Modules\Integrations\Application\Data\PaymeJsonRpcResponseData;
use App\Modules\Integrations\Application\Services\PaymeWebhookService;

final class HandlePaymeWebhookCommandHandler
{
    public function __construct(
        private readonly PaymeWebhookService $paymeWebhookService,
    ) {}

    public function handle(HandlePaymeWebhookCommand $command): PaymeJsonRpcResponseData
    {
        return $this->paymeWebhookService->process(
            authorization: $command->authorization,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
