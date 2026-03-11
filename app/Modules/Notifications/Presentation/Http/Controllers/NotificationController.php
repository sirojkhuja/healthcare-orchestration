<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SendNotificationCommand;
use App\Modules\Notifications\Application\Data\NotificationListCriteria;
use App\Modules\Notifications\Application\Handlers\GetNotificationQueryHandler;
use App\Modules\Notifications\Application\Handlers\ListNotificationsQueryHandler;
use App\Modules\Notifications\Application\Handlers\SendNotificationCommandHandler;
use App\Modules\Notifications\Application\Queries\GetNotificationQuery;
use App\Modules\Notifications\Application\Queries\ListNotificationsQuery;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NotificationController
{
    public function list(Request $request, ListNotificationsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($notification): array => $notification->toArray(),
                $handler->handle(new ListNotificationsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function send(Request $request, SendNotificationCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'uuid'],
            'recipient' => ['required', 'array'],
            'variables' => ['required', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $notification = $handler->handle(new SendNotificationCommand($validated));

        return response()->json([
            'status' => 'notification_queued',
            'data' => $notification->toArray(),
        ], 201);
    }

    public function show(string $notificationId, GetNotificationQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetNotificationQuery($notificationId))->toArray(),
        ]);
    }

    private function criteria(Request $request): NotificationListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(NotificationStatus::all())],
            'channel' => ['nullable', 'string', Rule::in(NotificationTemplateChannel::all())],
            'template_id' => ['nullable', 'uuid'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated);

        return new NotificationListCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            channel: $this->stringValue($validated, 'channel'),
            templateId: $this->stringValue($validated, 'template_id'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertDateRange(array $validated): void
    {
        $from = $this->stringValue($validated, 'created_from');
        $to = $this->stringValue($validated, 'created_to');

        if ($from !== null && $to !== null && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                'created_at' => ['The end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
