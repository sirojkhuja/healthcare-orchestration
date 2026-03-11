<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SetTelegramProviderCommand;
use App\Modules\Notifications\Application\Handlers\GetTelegramProviderQueryHandler;
use App\Modules\Notifications\Application\Handlers\SetTelegramProviderCommandHandler;
use App\Modules\Notifications\Application\Queries\GetTelegramProviderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationTelegramProviderController
{
    public function show(GetTelegramProviderQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTelegramProviderQuery)->toArray(),
        ]);
    }

    public function update(Request $request, SetTelegramProviderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'parse_mode' => ['required', 'string'],
            'broadcast_chat_ids' => ['present', 'array'],
            'support_chat_ids' => ['present', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'telegram_provider_updated',
            'data' => $handler->handle(new SetTelegramProviderCommand($validated))->toArray(),
        ]);
    }
}
