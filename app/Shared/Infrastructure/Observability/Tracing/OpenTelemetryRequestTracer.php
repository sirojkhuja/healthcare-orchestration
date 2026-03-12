<?php

namespace App\Shared\Infrastructure\Observability\Tracing;

use App\Shared\Application\Contracts\RequestTracer;
use App\Shared\Application\Contracts\RequestTraceSpan;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;

final class OpenTelemetryRequestTracer implements RequestTracer
{
    /**
     * @param  array<string, array<int, bool|int|float|string|null>|bool|int|float|string|null>  $attributes
     */
    #[\Override]
    public function startServerSpan(string $name, array $attributes = []): RequestTraceSpan
    {
        if (! config()->boolean('operations.tracing.enabled', false)) {
            return new NoopRequestTraceSpan;
        }

        $spanName = $name !== '' ? $name : 'http.request';
        $tracer = Globals::tracerProvider()->getTracer(config()->string('app.name'));
        $span = $tracer
            ->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes($this->normalizeAttributes($attributes))
            ->startSpan();
        $scope = $span->activate();

        return new OpenTelemetryRequestTraceSpan($span, $scope);
    }

    /**
     * @param  array<string, array<int, bool|int|float|string|null>|bool|int|float|string|null>  $attributes
     * @return array<string, array<int, bool|int|float|string>|bool|int|float|string>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            if ($key === '') {
                continue;
            }

            $attributeValue = $this->normalizeAttributeValue($value);

            if ($attributeValue === null) {
                continue;
            }

            $normalized[$key] = $attributeValue;
        }

        return $normalized;
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
