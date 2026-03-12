<?php

namespace App\Shared\Application\Contracts;

interface RequestTraceSpan
{
    public function traceId(): ?string;

    public function spanId(): ?string;

    /**
     * @param  array<int, bool|int|float|string|null>|bool|int|float|string|null  $value
     */
    public function setAttribute(string $key, bool|int|float|string|array|null $value): void;

    public function finish(?int $statusCode = null, ?\Throwable $exception = null): void;
}
