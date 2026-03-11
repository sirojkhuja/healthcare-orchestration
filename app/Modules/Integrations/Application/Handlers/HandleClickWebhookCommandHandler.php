<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandleClickWebhookCommand;
use App\Modules\Integrations\Application\Data\ClickWebhookResponseData;
use App\Modules\Integrations\Application\Services\ClickWebhookService;

final class HandleClickWebhookCommandHandler
{
    public function __construct(
        private readonly ClickWebhookService $clickWebhookService,
    ) {}

    public function handle(HandleClickWebhookCommand $command): ClickWebhookResponseData
    {
        return $this->clickWebhookService->process(
            rawPayload: $command->rawPayload,
            payload: $command->payload,
        );
    }
}
