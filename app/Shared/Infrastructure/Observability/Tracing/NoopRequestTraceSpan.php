<?php

namespace App\Shared\Infrastructure\Observability\Tracing;

use App\Shared\Application\Contracts\RequestTraceSpan;

final class NoopRequestTraceSpan implements RequestTraceSpan
{
    #[\Override]
    public function traceId(): ?string
    {
        return null;
    }

    #[\Override]
    public function spanId(): ?string
    {
        return null;
    }

    /**
     * @param  array<int, bool|int|float|string|null>|bool|int|float|string|null  $value
     */
    #[\Override]
    public function setAttribute(string $key, bool|int|float|string|array|null $value): void {}

    #[\Override]
    public function finish(?int $statusCode = null, ?\Throwable $exception = null): void {}
}
