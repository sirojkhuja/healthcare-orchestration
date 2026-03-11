<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\SendPlayMobileSmsCommand;
use App\Modules\Integrations\Application\Services\IntegrationSmsDispatchService;

final class SendPlayMobileSmsCommandHandler
{
    public function __construct(
        private readonly IntegrationSmsDispatchService $integrationSmsDispatchService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendPlayMobileSmsCommand $command): array
    {
        return $this->integrationSmsDispatchService->send('playmobile', $command->attributes);
    }
}
