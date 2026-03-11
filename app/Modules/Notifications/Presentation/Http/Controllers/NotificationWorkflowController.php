<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\CancelNotificationCommand;
use App\Modules\Notifications\Application\Commands\RetryNotificationCommand;
use App\Modules\Notifications\Application\Handlers\CancelNotificationCommandHandler;
use App\Modules\Notifications\Application\Handlers\RetryNotificationCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationWorkflowController
{
    public function cancel(
        string $notificationId,
        Request $request,
        CancelNotificationCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $notification = $handler->handle(new CancelNotificationCommand(
            notificationId: $notificationId,
            reason: $this->stringValue($validated, 'reason'),
        ));

        return response()->json([
            'status' => 'notification_canceled',
            'data' => $notification->toArray(),
        ]);
    }

    public function retry(string $notificationId, RetryNotificationCommandHandler $handler): JsonResponse
    {
        $notification = $handler->handle(new RetryNotificationCommand($notificationId));

        return response()->json([
            'status' => 'notification_retried',
            'data' => $notification->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? trim($validated[$key])
            : null;
    }
}
