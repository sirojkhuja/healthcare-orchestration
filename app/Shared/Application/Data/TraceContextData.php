<?php

namespace App\Shared\Application\Data;

final readonly class TraceContextData
{
    public function __construct(
        public ?string $traceId,
        public ?string $spanId,
    ) {}
}
