<?php

namespace App\Shared\Application\Data;

final readonly class OutboxLagMetrics
{
    public function __construct(
        public int $readyCount,
        public int $oldestReadyAgeSeconds,
    ) {}
}
