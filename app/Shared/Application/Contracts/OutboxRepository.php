<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\OutboxLagMetrics;
use App\Shared\Application\Data\OutboxMessage;
use DateTimeInterface;

interface OutboxRepository
{
    public function enqueue(OutboxMessage $message): void;

    /**
     * @return list<OutboxMessage>
     */
    public function claimReadyBatch(int $limit, DateTimeInterface $now): array;

    public function lagMetrics(DateTimeInterface $now): OutboxLagMetrics;

    public function markDelivered(string $outboxId, DateTimeInterface $deliveredAt): void;

    public function markFailed(string $outboxId, int $attempts, ?DateTimeInterface $nextAttemptAt, string $lastError): void;
}
