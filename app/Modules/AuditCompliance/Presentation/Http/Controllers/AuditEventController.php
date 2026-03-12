<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditEventSearchCriteria;
use App\Modules\AuditCompliance\Application\Handlers\ExportAuditEventsQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\GetAuditEventQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\ListAuditEventsQueryHandler;
use App\Modules\AuditCompliance\Application\Queries\ExportAuditEventsQuery;
use App\Modules\AuditCompliance\Application\Queries\GetAuditEventQuery;
use App\Modules\AuditCompliance\Application\Queries\ListAuditEventsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AuditEventController
{
    public function export(Request $request, ExportAuditEventsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            ...$this->searchRules(1000),
            'format' => ['nullable', 'string', 'in:csv'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'audit_export_created',
            'data' => $handler->handle(new ExportAuditEventsQuery(
                criteria: $this->criteriaFromValidated($validated, 1000),
                format: $this->stringValue($validated, 'format') ?? 'csv',
            ))->toArray(),
        ]);
    }

    public function list(Request $request, ListAuditEventsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->searchRules(100));
        /** @var array<string, mixed> $validated */
        $criteria = $this->criteriaFromValidated($validated, 50);

        return response()->json([
            'data' => array_map(
                static fn (AuditEventData $event): array => $event->toArray(),
                $handler->handle(new ListAuditEventsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $eventId, GetAuditEventQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetAuditEventQuery($eventId))->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function searchRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:160'],
            'action_prefix' => ['nullable', 'string', 'max:120'],
            'object_type' => ['nullable', 'string', 'max:64'],
            'object_id' => ['nullable', 'string', 'max:191'],
            'actor_type' => ['nullable', 'string', 'max:32'],
            'actor_id' => ['nullable', 'string', 'max:191'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function criteriaFromValidated(array $validated, int $defaultLimit): AuditEventSearchCriteria
    {
        $occurredFrom = $this->dateValue($validated, 'occurred_from');
        $occurredTo = $this->dateValue($validated, 'occurred_to');

        if ($occurredFrom instanceof CarbonImmutable && $occurredTo instanceof CarbonImmutable && $occurredFrom->gt($occurredTo)) {
            throw ValidationException::withMessages([
                'occurred_at' => ['The end timestamp must be on or after the start timestamp.'],
            ]);
        }

        return new AuditEventSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            actionPrefix: $this->stringValue($validated, 'action_prefix'),
            objectType: $this->stringValue($validated, 'object_type'),
            objectId: $this->stringValue($validated, 'object_id'),
            actorType: $this->stringValue($validated, 'actor_type'),
            actorId: $this->stringValue($validated, 'actor_id'),
            occurredFrom: $occurredFrom,
            occurredTo: $occurredTo,
            limit: $this->integerValue($validated, 'limit', $defaultLimit),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dateValue(array $validated, string $key): ?CarbonImmutable
    {
        $value = $this->stringValue($validated, $key);

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function integerValue(array $validated, string $key, int $default): int
    {
        return array_key_exists($key, $validated) && is_numeric($validated[$key])
            ? (int) $validated[$key]
            : $default;
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
