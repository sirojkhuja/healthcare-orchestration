<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandleUzumWebhookCommand;
use App\Modules\Integrations\Application\Data\UzumWebhookResponseData;
use App\Modules\Integrations\Application\Services\UzumWebhookService;

final class HandleUzumWebhookCommandHandler
{
    public function __construct(
        private readonly UzumWebhookService $uzumWebhookService,
    ) {}

    public function handle(HandleUzumWebhookCommand $command): UzumWebhookResponseData
    {
        return $this->uzumWebhookService->process(
            operation: $command->operation,
            authorization: $command->authorization,
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
