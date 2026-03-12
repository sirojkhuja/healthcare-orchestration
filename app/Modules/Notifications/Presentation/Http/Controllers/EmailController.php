<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SendEmailCommand;
use App\Modules\Notifications\Application\Data\EmailEventListCriteria;
use App\Modules\Notifications\Application\Handlers\ListEmailEventsQueryHandler;
use App\Modules\Notifications\Application\Handlers\SendEmailCommandHandler;
use App\Modules\Notifications\Application\Queries\ListEmailEventsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EmailController
{
    public function events(Request $request, ListEmailEventsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($event): array => $event->toArray(),
                $handler->handle(new ListEmailEventsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function send(Request $request, SendEmailCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $result = $handler->handle(new SendEmailCommand($validated));

        return response()->json($result);
    }

    private function criteria(Request $request): EmailEventListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', Rule::in(['notification', 'direct'])],
            'event_type' => ['nullable', 'string', Rule::in(['sent', 'failed'])],
            'notification_id' => ['nullable', 'uuid'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated);

        return new EmailEventListCriteria(
            query: $this->stringValue($validated, 'q'),
            source: $this->stringValue($validated, 'source'),
            eventType: $this->stringValue($validated, 'event_type'),
            notificationId: $this->stringValue($validated, 'notification_id'),
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
