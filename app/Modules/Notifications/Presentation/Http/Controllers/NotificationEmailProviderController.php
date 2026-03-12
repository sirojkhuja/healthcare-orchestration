<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SetEmailProviderCommand;
use App\Modules\Notifications\Application\Handlers\GetEmailProviderQueryHandler;
use App\Modules\Notifications\Application\Handlers\SetEmailProviderCommandHandler;
use App\Modules\Notifications\Application\Queries\GetEmailProviderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationEmailProviderController
{
    public function show(GetEmailProviderQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetEmailProviderQuery)->toArray(),
        ]);
    }

    public function update(Request $request, SetEmailProviderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'from_address' => ['required', 'email:rfc,dns'],
            'from_name' => ['required', 'string', 'max:191'],
            'reply_to_address' => ['sometimes', 'nullable', 'email:rfc,dns'],
            'reply_to_name' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'email_provider_updated',
            'data' => $handler->handle(new SetEmailProviderCommand($validated))->toArray(),
        ]);
    }
}
