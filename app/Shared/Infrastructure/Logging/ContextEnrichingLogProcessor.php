<?php

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Contracts\TraceContext;
use Monolog\LogRecord;
use Throwable;

final class ContextEnrichingLogProcessor
{
    public function __construct(
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly TenantContext $tenantContext,
        private readonly TraceContext $traceContext,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        if ($this->requestMetadataContext->hasCurrent()) {
            $metadata = $this->requestMetadataContext->current();
            $extra['request_id'] = $metadata->requestId;
            $extra['correlation_id'] = $metadata->correlationId;
            $extra['causation_id'] = $metadata->causationId;
        }

        if ($this->tenantContext->hasTenant()) {
            $extra['tenant_id'] = $this->tenantContext->tenantId();
            $extra['tenant_context_source'] = $this->tenantContext->source();
        }

        $trace = $this->traceContext->current();

        if ($trace->traceId !== null) {
            $extra['trace_id'] = $trace->traceId;
        }

        if ($trace->spanId !== null) {
            $extra['span_id'] = $trace->spanId;
        }

        try {
            $user = auth('api')->user();

            if ($user !== null) {
                /** @var mixed $actorId */
                $actorId = $user->getAuthIdentifier();
                /** @var mixed $actorName */
                $actorName = method_exists($user, 'getAttribute') ? $user->getAttribute('name') : null;
                /** @var mixed $actorEmail */
                $actorEmail = method_exists($user, 'getAttribute') ? $user->getAttribute('email') : null;

                if (is_scalar($actorId)) {
                    $extra['actor_id'] = (string) $actorId;
                }

                if (is_string($actorName) && $actorName !== '') {
                    $extra['actor_name'] = $actorName;
                }

                if (is_string($actorEmail) && $actorEmail !== '') {
                    $extra['actor_email'] = $actorEmail;
                }
            }
        } catch (Throwable) {
            // Logging must never fail request processing.
        }

        return $record->with(extra: $extra);
    }
}
