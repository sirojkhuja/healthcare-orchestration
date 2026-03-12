<?php

namespace App\Shared\Infrastructure\Observability\Context;

use App\Shared\Application\Contracts\TraceContext;
use App\Shared\Application\Data\TraceContextData;
use Illuminate\Support\Facades\Context;

final class ContextBackedTraceContext implements TraceContext
{
    #[\Override]
    public function setCurrent(?string $traceId, ?string $spanId): void
    {
        if ($traceId === null && $spanId === null) {
            $this->clear();

            return;
        }

        Context::add([
            'trace_id' => $traceId,
            'span_id' => $spanId,
        ]);
    }

    #[\Override]
    public function current(): TraceContextData
    {
        /** @var array<string, mixed> $context */
        $context = Context::only(['trace_id', 'span_id']);
        /** @var mixed $traceId */
        $traceId = $context['trace_id'] ?? null;
        /** @var mixed $spanId */
        $spanId = $context['span_id'] ?? null;

        return new TraceContextData(
            traceId: is_string($traceId) && $traceId !== '' ? $traceId : null,
            spanId: is_string($spanId) && $spanId !== '' ? $spanId : null,
        );
    }

    #[\Override]
    public function clear(): void
    {
        Context::forget([
            'trace_id',
            'span_id',
        ]);
    }
}
