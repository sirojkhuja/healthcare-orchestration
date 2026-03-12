<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\HandleMyIdWebhookCommand;
use App\Modules\Integrations\Application\Services\MyIdVerificationService;

final class HandleMyIdWebhookCommandHandler
{
    public function __construct(
        private readonly MyIdVerificationService $myIdVerificationService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function handle(HandleMyIdWebhookCommand $command): array
    {
        return $this->myIdVerificationService->processWebhook($command->secret, $command->rawPayload, $command->payload);
    }
}
