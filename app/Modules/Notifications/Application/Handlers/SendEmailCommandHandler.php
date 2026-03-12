<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SendEmailCommand;
use App\Modules\Notifications\Application\Services\EmailDirectSendService;

final class SendEmailCommandHandler
{
    public function __construct(
        private readonly EmailDirectSendService $emailDirectSendService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendEmailCommand $command): array
    {
        return $this->emailDirectSendService->send($command->attributes);
    }
}
