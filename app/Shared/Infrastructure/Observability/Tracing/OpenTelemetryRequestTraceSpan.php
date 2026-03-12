<?php

namespace App\Shared\Infrastructure\Observability\Tracing;

use App\Shared\Application\Contracts\RequestTraceSpan;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

final class OpenTelemetryRequestTraceSpan implements RequestTraceSpan
{
    public function __construct(
        private readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
    ) {}

    #[\Override]
    public function traceId(): ?string
    {
        $context = $this->span->getContext();

        return $context->isValid() ? $context->getTraceId() : null;
    }

    #[\Override]
    public function spanId(): ?string
    {
        $context = $this->span->getContext();

        return $context->isValid() ? $context->getSpanId() : null;
    }

    /**
     * @param  array<int, bool|int|float|string|null>|bool|int|float|string|null  $value
     */
    #[\Override]
    public function setAttribute(string $key, bool|int|float|string|array|null $value): void
    {
        if ($key === '') {
            return;
        }

        $normalized = $this->normalizeAttributeValue($value);

        if ($normalized === null) {
            return;
        }

        $this->span->setAttribute($key, $normalized);
    }

    #[\Override]
    public function finish(?int $statusCode = null, ?\Throwable $exception = null): void
    {
        if ($exception !== null) {
            $this->span->recordException($exception, ['exception.escaped' => true]);
            $this->span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        } elseif ($statusCode !== null && $statusCode >= 500) {
            $this->span->setStatus(StatusCode::STATUS_ERROR, sprintf('HTTP %d', $statusCode));
        }

        $this->scope->detach();
        $this->span->end();
    }

    /**
     * @param  array<int, bool|int|float|string|null>|bool|int|float|string|null  $value
     * @return array<int, bool|int|float|string>|bool|int|float|string|null
     */
    private function normalizeAttributeValue(bool|int|float|string|array|null $value): bool|int|float|string|array|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_bool($item) || is_int($item) || is_float($item) || is_string($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}
