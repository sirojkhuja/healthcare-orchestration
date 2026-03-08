<?php

namespace App\Shared\Application\Data;

final readonly class KafkaConsumerDispatchResult
{
    private function __construct(public bool $processed) {}

    public static function processed(): self
    {
        return new self(true);
    }

    public static function skipped(): self
    {
        return new self(false);
    }
}
