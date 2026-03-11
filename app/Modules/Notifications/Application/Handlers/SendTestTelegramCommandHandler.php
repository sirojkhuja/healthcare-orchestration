<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SendTestTelegramCommand;
use App\Modules\Notifications\Application\Services\TelegramDiagnosticSendService;

final class SendTestTelegramCommandHandler
{
    public function __construct(
        private readonly TelegramDiagnosticSendService $telegramDiagnosticSendService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendTestTelegramCommand $command): array
    {
        return $this->telegramDiagnosticSendService->send($command->attributes);
    }
}
