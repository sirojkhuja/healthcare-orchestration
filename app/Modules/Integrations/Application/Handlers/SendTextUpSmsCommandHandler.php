<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\SendTextUpSmsCommand;
use App\Modules\Integrations\Application\Services\IntegrationSmsDispatchService;

final class SendTextUpSmsCommandHandler
{
    public function __construct(
        private readonly IntegrationSmsDispatchService $integrationSmsDispatchService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendTextUpSmsCommand $command): array
    {
        return $this->integrationSmsDispatchService->send('textup', $command->attributes);
    }
}
