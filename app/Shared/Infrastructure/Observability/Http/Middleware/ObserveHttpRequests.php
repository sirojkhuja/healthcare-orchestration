<?php

namespace App\Shared\Infrastructure\Observability\Http\Middleware;

use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\RequestTracer;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Contracts\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ObserveHttpRequests
{
    public function __construct(
        private readonly RequestTracer $requestTracer,
        private readonly ObservabilityMetricRecorder $metricRecorder,
        private readonly TraceContext $traceContext,
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $route = $request->route();
        $namedRoute = $route->getName();
        $routeName = is_string($namedRoute) && $namedRoute !== ''
            ? $namedRoute
            : sprintf('%s %s', $request->method(), '/'.ltrim($request->path(), '/'));
        $span = $this->requestTracer->startServerSpan($routeName, [
            'http.request.method' => $request->method(),
            'http.route' => $routeName,
            'url.path' => '/'.ltrim($request->path(), '/'),
            'tenant.id' => $this->tenantContext->tenantId(),
        ]);

        $this->traceContext->setCurrent($span->traceId(), $span->spanId());
        $this->configureSentryScope($request, $routeName, $span->traceId(), $span->spanId());

        Log::info('http.request.started', $this->requestLogContext($request, $routeName));

        $statusCode = 500;
        $exception = null;

        try {
            /** @var mixed $nextResponse */
            $nextResponse = $next($request);

            if (! $nextResponse instanceof Response) {
                throw new \UnexpectedValueException('Expected a Response instance from the HTTP pipeline.');
            }

            $response = $nextResponse;
            $statusCode = $response->getStatusCode();

            return $response;
        } catch (Throwable $caught) {
            $exception = $caught;

            throw $caught;
        } finally {
            $durationSeconds = max(0.0, microtime(true) - $startedAt);
            $durationMilliseconds = (int) round($durationSeconds * 1000.0);
            $this->metricRecorder->recordHttpRequest(
                method: $request->method(),
                route: $routeName,
                statusCode: $statusCode,
                durationSeconds: $durationSeconds,
            );

            $span->setAttribute('http.response.status_code', $statusCode);
            $span->setAttribute('medflow.request.duration_ms', $durationMilliseconds);
            $span->finish($statusCode, $exception);

            $level = $statusCode >= 500 || $exception !== null ? 'error' : 'info';
            Log::log($level, 'http.request.completed', $this->requestLogContext($request, $routeName) + [
                'status_code' => $statusCode,
                'duration_ms' => $durationMilliseconds,
            ]);

            if ($exception !== null) {
                $this->configureSentryScope($request, $routeName, $span->traceId(), $span->spanId(), $exception);
            }

            $this->traceContext->clear();
        }
    }

    private function configureSentryScope(
        Request $request,
        string $routeName,
        ?string $traceId,
        ?string $spanId,
        ?Throwable $exception = null,
    ): void {
        if (! class_exists(\Sentry\State\Scope::class) || ! function_exists('\Sentry\configureScope')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($request, $routeName, $traceId, $spanId, $exception): void {
            if ($this->requestMetadataContext->hasCurrent()) {
                $metadata = $this->requestMetadataContext->current();
                $scope->setTag('request_id', $metadata->requestId);
                $scope->setTag('correlation_id', $metadata->correlationId);
                $scope->setTag('causation_id', $metadata->causationId);
            }

            if ($this->tenantContext->hasTenant()) {
                $scope->setTag('tenant_id', (string) $this->tenantContext->tenantId());
            }

            $scope->setTag('route', $routeName);
            $scope->setTag('http_method', $request->method());

            if ($traceId !== null) {
                $scope->setTag('trace_id', $traceId);
            }

            if ($spanId !== null) {
                $scope->setTag('span_id', $spanId);
            }

            if ($exception !== null) {
                $scope->setExtra('exception_class', $exception::class);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requestLogContext(Request $request, string $routeName): array
    {
        return [
            'route' => $routeName,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'tenant_id' => $this->tenantContext->tenantId(),
        ];
    }
}
