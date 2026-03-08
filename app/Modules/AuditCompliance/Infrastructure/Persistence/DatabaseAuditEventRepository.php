<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Data\AuditActor;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DatabaseAuditEventRepository implements AuditEventRepository
{
    #[\Override]
    public function append(AuditEventData $event): void
    {
        AuditEventRecord::query()->create([
            'id' => $event->eventId,
            'tenant_id' => $event->tenantId,
            'action' => $event->action,
            'object_type' => $event->objectType,
            'object_id' => $event->objectId,
            'actor_type' => $event->actor->type,
            'actor_id' => $event->actor->id,
            'actor_name' => $event->actor->name,
            'request_id' => $event->requestId,
            'correlation_id' => $event->correlationId,
            'before_values' => $event->before,
            'after_values' => $event->after,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurredAt,
            'created_at' => $event->occurredAt,
        ]);
    }

    #[\Override]
    public function findById(string $eventId): ?AuditEventData
    {
        $record = AuditEventRecord::query()->find($eventId);

        return $record instanceof AuditEventRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function forObject(string $objectType, string $objectId, ?string $tenantId = null): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, AuditEventRecord> $records */
        $records = $this->queryForObject($objectType, $objectId, $tenantId)
            ->orderBy('occurred_at')
            ->get();

        /** @var list<AuditEventData> $events */
        $events = array_values(array_map(
            fn (AuditEventRecord $record) => $this->toData($record),
            $records->all(),
        ));

        return $events;
    }

    #[\Override]
    public function pruneOlderThan(CarbonImmutable $cutoff): int
    {
        /** @psalm-suppress MixedAssignment */
        $deleted = AuditEventRecord::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @return Builder<AuditEventRecord>
     */
    private function queryForObject(string $objectType, string $objectId, ?string $tenantId): Builder
    {
        $query = AuditEventRecord::query()
            ->where('object_type', $objectType)
            ->where('object_id', $objectId);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    private function toData(AuditEventRecord $record): AuditEventData
    {
        return new AuditEventData(
            eventId: $this->stringValue($record->getAttribute('id')),
            tenantId: $this->nullableString($record->getAttribute('tenant_id')),
            action: $this->stringValue($record->getAttribute('action')),
            objectType: $this->stringValue($record->getAttribute('object_type')),
            objectId: $this->stringValue($record->getAttribute('object_id')),
            actor: new AuditActor(
                type: $this->stringValue($record->getAttribute('actor_type')),
                id: $this->nullableString($record->getAttribute('actor_id')),
                name: $this->nullableString($record->getAttribute('actor_name')),
            ),
            requestId: $this->stringValue($record->getAttribute('request_id')),
            correlationId: $this->stringValue($record->getAttribute('correlation_id')),
            before: $this->arrayValue($record->getAttribute('before_values')),
            after: $this->arrayValue($record->getAttribute('after_values')),
            metadata: $this->arrayValue($record->getAttribute('metadata')),
            occurredAt: CarbonImmutable::parse($this->stringValue($record->getAttribute('occurred_at'))),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
