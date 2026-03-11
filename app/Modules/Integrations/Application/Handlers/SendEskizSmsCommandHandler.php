<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\SendEskizSmsCommand;
use App\Modules\Integrations\Application\Services\IntegrationSmsDispatchService;

final class SendEskizSmsCommandHandler
{
    public function __construct(
        private readonly IntegrationSmsDispatchService $integrationSmsDispatchService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendEskizSmsCommand $command): array
    {
        return $this->integrationSmsDispatchService->send('eskiz', $command->attributes);
    }
}
