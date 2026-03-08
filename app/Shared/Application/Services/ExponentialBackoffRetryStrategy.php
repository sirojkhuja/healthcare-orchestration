<?php

namespace App\Shared\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ExponentialBackoffRetryStrategy
{
    public function nextAttemptAt(int $attempt, DateTimeInterface $failedAt): CarbonImmutable
    {
        /** @psalm-suppress MixedAssignment */
        $baseSeconds = config('medflow.kafka.outbox.backoff_seconds', 5);
        /** @psalm-suppress MixedAssignment */
        $maxSeconds = config('medflow.kafka.outbox.backoff_max_seconds', 300);

        $base = is_numeric($baseSeconds) && (int) $baseSeconds > 0 ? (int) $baseSeconds : 5;
        $max = is_numeric($maxSeconds) && (int) $maxSeconds >= $base ? (int) $maxSeconds : 300;
        $multiplier = 1 << max($attempt - 1, 0);
        $delay = min($max, $base * $multiplier);

        return CarbonImmutable::instance($failedAt)->addSeconds($delay);
    }
}
