<?php

namespace App\Shared\Application\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class OutboxMessage
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $outboxId,
        public string $eventId,
        public string $eventType,
        public string $topic,
        public ?string $tenantId,
        public string $requestId,
        public string $correlationId,
        public ?string $causationId,
        public ?string $partitionKey,
        public array $headers,
        public array $payload,
        public int $attempts = 0,
        public string $status = 'pending',
        public ?DateTimeInterface $nextAttemptAt = null,
        public ?DateTimeInterface $claimedAt = null,
        public ?DateTimeInterface $deliveredAt = null,
        public ?string $lastError = null,
        public ?DateTimeInterface $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->outboxId,
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'topic' => $this->topic,
            'tenant_id' => $this->tenantId,
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'partition_key' => $this->partitionKey,
            'headers' => $this->headers,
            'payload' => $this->payload,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'next_attempt_at' => $this->formatDate($this->nextAttemptAt),
            'claimed_at' => $this->formatDate($this->claimedAt),
            'delivered_at' => $this->formatDate($this->deliveredAt),
            'last_error' => $this->lastError,
            'created_at' => $this->formatDate($this->createdAt),
        ];
    }

    private function formatDate(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::instance($value)->toIso8601String();
    }
}
