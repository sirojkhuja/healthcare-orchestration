<?php

namespace App\Shared\Application\Services;

use App\Shared\Application\Contracts\KafkaProducer;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Data\OutboxRelayResult;
use Carbon\CarbonImmutable;
use Throwable;

final class OutboxRelay
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly KafkaProducer $kafkaProducer,
        private readonly ExponentialBackoffRetryStrategy $retryStrategy,
    ) {}

    public function drain(int $limit): OutboxRelayResult
    {
        $now = CarbonImmutable::now();
        $messages = $this->outboxRepository->claimReadyBatch($limit, $now);
        $delivered = 0;
        $failed = 0;

        foreach ($messages as $message) {
            try {
                $this->kafkaProducer->publish($message);
                $this->outboxRepository->markDelivered($message->outboxId, $now);
                $delivered++;
            } catch (Throwable $throwable) {
                $failedAttempts = $message->attempts + 1;
                $nextAttemptAt = $failedAttempts >= $this->maxAttempts()
                    ? null
                    : $this->retryStrategy->nextAttemptAt($failedAttempts, $now);

                $this->outboxRepository->markFailed(
                    outboxId: $message->outboxId,
                    attempts: $failedAttempts,
                    nextAttemptAt: $nextAttemptAt,
                    lastError: $throwable->getMessage(),
                );
                $failed++;
            }
        }

        return new OutboxRelayResult(
            claimed: count($messages),
            delivered: $delivered,
            failed: $failed,
        );
    }

    private function maxAttempts(): int
    {
        /** @psalm-suppress MixedAssignment */
        $configuredAttempts = config('medflow.kafka.outbox.max_attempts', 10);

        if (is_numeric($configuredAttempts) && (int) $configuredAttempts > 0) {
            return (int) $configuredAttempts;
        }

        return 10;
    }
}
