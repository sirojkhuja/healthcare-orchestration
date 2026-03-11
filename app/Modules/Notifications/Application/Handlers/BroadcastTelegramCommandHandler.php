<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\BroadcastTelegramCommand;
use App\Modules\Notifications\Application\Data\TelegramBroadcastResultData;
use App\Modules\Notifications\Application\Services\TelegramBroadcastService;

final class BroadcastTelegramCommandHandler
{
    public function __construct(
        private readonly TelegramBroadcastService $telegramBroadcastService,
    ) {}

    public function handle(BroadcastTelegramCommand $command): TelegramBroadcastResultData
    {
        return $this->telegramBroadcastService->broadcast($command->attributes);
    }
}
