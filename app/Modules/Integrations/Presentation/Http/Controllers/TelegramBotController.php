<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\SyncTelegramBotCommand;
use App\Modules\Integrations\Application\Handlers\SyncTelegramBotCommandHandler;
use Illuminate\Http\JsonResponse;

final class TelegramBotController
{
    public function sync(SyncTelegramBotCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'telegram_bot_synced',
            'data' => $handler->handle(new SyncTelegramBotCommand)->toArray(),
        ]);
    }
}
