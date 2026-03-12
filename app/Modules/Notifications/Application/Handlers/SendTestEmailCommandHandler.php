<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SendTestEmailCommand;
use App\Modules\Notifications\Application\Services\EmailDiagnosticSendService;

final class SendTestEmailCommandHandler
{
    public function __construct(
        private readonly EmailDiagnosticSendService $emailDiagnosticSendService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendTestEmailCommand $command): array
    {
        return $this->emailDiagnosticSendService->send($command->attributes);
    }
}
