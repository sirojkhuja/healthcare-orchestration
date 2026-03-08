<?php

namespace App\Shared\Application\Data;

final readonly class OutboxRelayResult
{
    public function __construct(
        public int $claimed,
        public int $delivered,
        public int $failed,
    ) {}
}
