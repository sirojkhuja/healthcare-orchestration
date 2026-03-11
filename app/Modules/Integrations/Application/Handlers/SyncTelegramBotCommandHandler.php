<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\SyncTelegramBotCommand;
use App\Modules\Integrations\Application\Services\TelegramBotSyncService;
use App\Modules\Notifications\Application\Data\TelegramSyncResultData;

final class SyncTelegramBotCommandHandler
{
    public function __construct(
        private readonly TelegramBotSyncService $telegramBotSyncService,
    ) {}

    public function handle(SyncTelegramBotCommand $command): TelegramSyncResultData
    {
        return $this->telegramBotSyncService->sync();
    }
}
