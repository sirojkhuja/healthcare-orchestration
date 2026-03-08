<?php

namespace App\Shared\Application\Data;

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
}
