<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\TraceContextData;

interface TraceContext
{
    public function setCurrent(?string $traceId, ?string $spanId): void;

    public function current(): TraceContextData;

    public function clear(): void;
}
