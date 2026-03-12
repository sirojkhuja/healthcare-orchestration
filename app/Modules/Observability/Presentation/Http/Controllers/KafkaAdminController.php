<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\ReplayKafkaEventsCommand;
use App\Modules\Observability\Application\Handlers\GetKafkaLagQueryHandler;
use App\Modules\Observability\Application\Handlers\ReplayKafkaEventsCommandHandler;
use App\Modules\Observability\Application\Queries\GetKafkaLagQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class KafkaAdminController
{
    public function lag(GetKafkaLagQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetKafkaLagQuery)->toArray(),
        ]);
    }

    public function replay(Request $request, ReplayKafkaEventsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'consumer_name' => ['required', 'string', 'max:191'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['string', 'max:191'],
            'processed_before' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        /** @var array<string, mixed> $validated */
        $eventIds = $this->stringArray($validated, 'event_ids');
        $processedBefore = $this->nullableDate($validated, 'processed_before');

        if (($eventIds === null || $eventIds === []) && ! $processedBefore instanceof CarbonImmutable) {
            throw ValidationException::withMessages([
                'event_ids' => ['Provide event_ids or processed_before to enable replay.'],
            ]);
        }

        return response()->json([
            'status' => 'kafka_replay_enabled',
            'data' => $handler->handle(new ReplayKafkaEventsCommand(
                consumerName: $this->requiredString($validated, 'consumer_name'),
                eventIds: $eventIds,
                processedBefore: $processedBefore,
                limit: is_numeric($validated['limit'] ?? null) ? (int) $validated['limit'] : 100,
            ))->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableDate(array $validated, string $key): ?CarbonImmutable
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? CarbonImmutable::parse($validated[$key])
            : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>|null
     */
    private function stringArray(array $validated, string $key): ?array
    {
        if (! array_key_exists($key, $validated) || ! is_array($validated[$key])) {
            return null;
        }

        return array_values(array_filter($validated[$key], 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function requiredString(array $validated, string $key): string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) ? $validated[$key] : '';
    }
}
