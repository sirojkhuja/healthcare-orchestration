<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SetSmsProvidersPriorityCommand;
use App\Modules\Notifications\Application\Handlers\ListSmsProvidersQueryHandler;
use App\Modules\Notifications\Application\Handlers\SetSmsProvidersPriorityCommandHandler;
use App\Modules\Notifications\Application\Queries\ListSmsProvidersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationSmsProviderController
{
    public function list(ListSmsProvidersQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new ListSmsProvidersQuery)->toArray(),
        ]);
    }

    public function update(Request $request, SetSmsProvidersPriorityCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'routes' => ['required', 'array', 'min:1'],
            'routes.*.message_type' => ['required', 'string'],
            'routes.*.providers' => ['required', 'array', 'min:1'],
            'routes.*.providers.*' => ['required', 'string'],
        ]);
        /** @var array<string, mixed> $validated */
        $settings = $handler->handle(new SetSmsProvidersPriorityCommand(
            routes: $this->routes($validated['routes'] ?? null),
        ));

        return response()->json([
            'status' => 'sms_providers_updated',
            'data' => $settings->toArray(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(mixed $routes): array
    {
        if (! is_array($routes)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($routes as $route) {
            if (is_array($route)) {
                /** @var array<string, mixed> $route */
                $normalized[] = $route;
            }
        }

        return $normalized;
    }
}
