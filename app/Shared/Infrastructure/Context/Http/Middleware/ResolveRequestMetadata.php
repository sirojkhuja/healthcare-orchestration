<?php

namespace App\Shared\Infrastructure\Context\Http\Middleware;

use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\RequestMetadata;
use App\Shared\Infrastructure\Context\RequestMetadataHeaderResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ResolveRequestMetadata
{
    public function __construct(
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly RequestMetadataHeaderResolver $headerResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $headerNames = $this->headerResolver->resolve();
        $requestId = $this->resolveInboundId($request->header($headerNames['request_id'])) ?? (string) Str::uuid();
        $correlationId = $this->resolveInboundId($request->header($headerNames['correlation_id'])) ?? $requestId;
        $causationId = $this->resolveInboundId($request->header($headerNames['causation_id'])) ?? $requestId;

        $metadata = new RequestMetadata(
            requestId: $requestId,
            correlationId: $correlationId,
            causationId: $causationId,
        );

        $this->requestMetadataContext->initialize($metadata);
        $request->attributes->add($metadata->toArray());

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new LogicException('Request metadata middleware must return an HTTP response.');
        }

        $resolvedHeaderValues = [
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'causation_id' => $metadata->causationId,
        ];

        foreach ($headerNames as $contextKey => $headerName) {
            $response->headers->set($headerName, $resolvedHeaderValues[$contextKey]);
        }

        return $response;
    }

    private function resolveInboundId(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! Str::isUuid($value)) {
            return null;
        }

        return strtolower($value);
    }
}
