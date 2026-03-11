<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\BroadcastTelegramCommand;
use App\Modules\Notifications\Application\Handlers\BroadcastTelegramCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TelegramBroadcastController
{
    public function broadcast(Request $request, BroadcastTelegramCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'chat_ids' => ['sometimes', 'array'],
            'parse_mode' => ['sometimes', 'string'],
            'audience' => ['sometimes', 'string'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'telegram_broadcast_processed',
            'data' => $handler->handle(new BroadcastTelegramCommand($validated))->toArray(),
        ]);
    }
}
