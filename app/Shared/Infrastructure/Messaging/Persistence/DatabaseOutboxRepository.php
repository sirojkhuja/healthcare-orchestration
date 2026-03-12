<?php

namespace App\Shared\Infrastructure\Messaging\Persistence;

use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Data\OutboxLagMetrics;
use App\Shared\Application\Data\OutboxMessage;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DatabaseOutboxRepository implements OutboxRepository
{
    #[\Override]
    public function enqueue(OutboxMessage $message): void
    {
        OutboxMessageRecord::query()->create([
            'id' => $message->outboxId,
            'event_id' => $message->eventId,
            'event_type' => $message->eventType,
            'topic' => $message->topic,
            'tenant_id' => $message->tenantId,
            'request_id' => $message->requestId,
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'partition_key' => $message->partitionKey,
            'headers' => $message->headers,
            'payload' => $message->payload,
            'status' => OutboxMessageRecord::STATUS_PENDING,
            'attempts' => $message->attempts,
            'next_attempt_at' => $message->nextAttemptAt,
            'claimed_at' => $message->claimedAt,
            'delivered_at' => $message->deliveredAt,
            'last_error' => $message->lastError,
            'created_at' => $message->createdAt ?? now(),
            'updated_at' => now(),
        ]);
    }

    #[\Override]
    public function findForAdmin(string $outboxId, ?string $tenantId): ?OutboxMessage
    {
        $query = OutboxMessageRecord::query()->whereKey($outboxId);

        if ($tenantId !== null) {
            $query->where(function (Builder $builder) use ($tenantId): void {
                $builder->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        }

        $record = $query->first();

        return $record instanceof OutboxMessageRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function claimReadyBatch(int $limit, DateTimeInterface $now): array
    {
        /** @var list<OutboxMessage> $messages */
        $messages = DB::transaction(function () use ($limit, $now): array {
            $query = OutboxMessageRecord::query()
                ->whereIn('status', [OutboxMessageRecord::STATUS_PENDING, OutboxMessageRecord::STATUS_FAILED])
                ->where(function (Builder $builder) use ($now): void {
                    $builder->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', $now);
                })
                ->orderBy('created_at')
                ->limit($limit);

            if (DB::getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, OutboxMessageRecord> $records */
            $records = $query->get();

            foreach ($records as $record) {
                $record->update([
                    'status' => OutboxMessageRecord::STATUS_PROCESSING,
                    'claimed_at' => $now,
                ]);
            }

            /** @var list<OutboxMessageRecord> $recordItems */
            $recordItems = $records->all();

            return array_map(fn (OutboxMessageRecord $record): OutboxMessage => $this->toData($record), $recordItems);
        });

        return $messages;
    }

    #[\Override]
    public function listForAdmin(?string $tenantId, ?string $status, ?string $topic, ?string $eventType, int $limit): array
    {
        $query = OutboxMessageRecord::query();

        if ($tenantId !== null) {
            $query->where(function (Builder $builder) use ($tenantId): void {
                $builder->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($topic !== null) {
            $query->where('topic', $topic);
        }

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        if (DB::getDriverName() === 'pgsql') {
            $query->orderByRaw(
                "CASE status WHEN 'pending' THEN 1 WHEN 'failed' THEN 2 WHEN 'processing' THEN 3 WHEN 'delivered' THEN 4 ELSE 5 END",
            );
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, OutboxMessageRecord> $records */
        $records = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        /** @var list<OutboxMessageRecord> $recordItems */
        $recordItems = $records->all();

        return array_map(fn (OutboxMessageRecord $record): OutboxMessage => $this->toData($record), $recordItems);
    }

    #[\Override]
    public function lagMetrics(DateTimeInterface $now): OutboxLagMetrics
    {
        $query = OutboxMessageRecord::query()
            ->whereIn('status', [OutboxMessageRecord::STATUS_PENDING, OutboxMessageRecord::STATUS_FAILED])
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', $now);
            });

        /** @var OutboxMessageRecord|null $oldestReady */
        $oldestReady = (clone $query)->orderBy('created_at')->first();
        $oldestCreatedAt = $oldestReady?->created_at;
        $oldestAge = $oldestCreatedAt instanceof CarbonImmutable
            ? (int) CarbonImmutable::instance($now)->diffInSeconds($oldestCreatedAt)
            : 0;

        return new OutboxLagMetrics(
            readyCount: $query->count(),
            oldestReadyAgeSeconds: $oldestAge,
        );
    }

    #[\Override]
    public function markDelivered(string $outboxId, DateTimeInterface $deliveredAt): void
    {
        OutboxMessageRecord::query()
            ->whereKey($outboxId)
            ->update([
                'status' => OutboxMessageRecord::STATUS_DELIVERED,
                'delivered_at' => $deliveredAt,
                'updated_at' => now(),
                'last_error' => null,
            ]);
    }

    #[\Override]
    public function markFailed(string $outboxId, int $attempts, ?DateTimeInterface $nextAttemptAt, string $lastError): void
    {
        OutboxMessageRecord::query()
            ->whereKey($outboxId)
            ->update([
                'status' => OutboxMessageRecord::STATUS_FAILED,
                'attempts' => $attempts,
                'next_attempt_at' => $nextAttemptAt,
                'last_error' => $lastError,
                'updated_at' => now(),
            ]);
    }

    #[\Override]
    public function retry(string $outboxId): ?OutboxMessage
    {
        OutboxMessageRecord::query()
            ->whereKey($outboxId)
            ->update([
                'status' => OutboxMessageRecord::STATUS_PENDING,
                'next_attempt_at' => null,
                'claimed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $record = OutboxMessageRecord::query()->find($outboxId);

        return $record instanceof OutboxMessageRecord ? $this->toData($record) : null;
    }

    private function toData(OutboxMessageRecord $record): OutboxMessage
    {
        return new OutboxMessage(
            outboxId: $record->id,
            eventId: $record->event_id,
            eventType: $record->event_type,
            topic: $record->topic,
            tenantId: $record->tenant_id,
            requestId: $record->request_id,
            correlationId: $record->correlation_id,
            causationId: $record->causation_id,
            partitionKey: $record->partition_key,
            headers: $record->headers ?? [],
            payload: $record->payload,
            attempts: $record->attempts,
            status: $record->status,
            nextAttemptAt: $record->next_attempt_at,
            claimedAt: $record->claimed_at,
            deliveredAt: $record->delivered_at,
            lastError: $record->last_error,
            createdAt: $record->created_at,
        );
    }
}
