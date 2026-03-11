<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SendTestSmsCommand;
use App\Modules\Notifications\Application\Services\SmsDiagnosticSendService;

final class SendTestSmsCommandHandler
{
    public function __construct(
        private readonly SmsDiagnosticSendService $smsDiagnosticSendService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendTestSmsCommand $command): array
    {
        return $this->smsDiagnosticSendService->send($command->attributes);
    }
}
