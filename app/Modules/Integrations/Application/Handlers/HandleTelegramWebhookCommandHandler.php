<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandleTelegramWebhookCommand;
use App\Modules\Integrations\Application\Services\TelegramWebhookService;

final class HandleTelegramWebhookCommandHandler
{
    public function __construct(
        private readonly TelegramWebhookService $telegramWebhookService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function handle(HandleTelegramWebhookCommand $command): array
    {
        return $this->telegramWebhookService->process(
            $command->secretToken,
            $command->rawPayload,
            $command->payload,
        );
    }
}
