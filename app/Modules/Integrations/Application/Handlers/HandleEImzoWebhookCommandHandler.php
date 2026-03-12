<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandleEImzoWebhookCommand;
use App\Modules\Integrations\Application\Services\EImzoSigningService;

final class HandleEImzoWebhookCommandHandler
{
    public function __construct(
        private readonly EImzoSigningService $eImzoSigningService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function handle(HandleEImzoWebhookCommand $command): array
    {
        return $this->eImzoSigningService->processWebhook($command->secret, $command->rawPayload, $command->payload);
    }
}
