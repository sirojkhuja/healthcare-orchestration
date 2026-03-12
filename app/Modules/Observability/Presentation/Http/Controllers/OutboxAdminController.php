<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\DrainOutboxCommand;
use App\Modules\Observability\Application\Commands\RetryOutboxItemCommand;
use App\Modules\Observability\Application\Data\OutboxSearchCriteria;
use App\Modules\Observability\Application\Handlers\DrainOutboxCommandHandler;
use App\Modules\Observability\Application\Handlers\ListOutboxQueryHandler;
use App\Modules\Observability\Application\Handlers\RetryOutboxItemCommandHandler;
use App\Modules\Observability\Application\Queries\ListOutboxQuery;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OutboxAdminController
{
    public function drain(Request $request, DrainOutboxCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'outbox_drained',
            'data' => $handler->handle(new DrainOutboxCommand(
                is_numeric($validated['limit'] ?? null) ? (int) $validated['limit'] : config()->integer('medflow.kafka.outbox.batch_size', 50),
            ))->toArray(),
        ]);
    }

    public function list(Request $request, ListOutboxQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,processing,failed,delivered'],
            'topic' => ['nullable', 'string', 'max:191'],
            'event_type' => ['nullable', 'string', 'max:191'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'data' => array_map(
                static fn (OutboxMessage $message): array => $message->toArray(),
                $handler->handle(new ListOutboxQuery(
                    new OutboxSearchCriteria(
                        status: $this->nullableString($validated, 'status'),
                        topic: $this->nullableString($validated, 'topic'),
                        eventType: $this->nullableString($validated, 'event_type'),
                        limit: is_numeric($validated['limit'] ?? null) ? (int) $validated['limit'] : 50,
                    ),
                )),
            ),
        ]);
    }

    public function retry(string $outboxId, RetryOutboxItemCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'outbox_item_retried',
            'data' => $handler->handle(new RetryOutboxItemCommand($outboxId))->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? trim($validated[$key])
            : null;
    }
}
