<?php

namespace App\Shared\Infrastructure\Tenancy\Http\Middleware;

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Exceptions\InvalidTenantContext;
use App\Shared\Application\Exceptions\TenantScopeViolation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('medflow.tenancy.header', 'X-Tenant-Id');

        if (! is_string($headerName) || $headerName === '') {
            throw new InvalidTenantContext('The configured tenant header must be a non-empty string.');
        }

        $headerTenantId = $this->normalizeTenantId($request->header($headerName), $headerName);
        $routeTenantId = $this->resolveRouteTenantId($request);

        if ($headerTenantId !== null && $routeTenantId !== null && $headerTenantId !== $routeTenantId) {
            throw new TenantScopeViolation('Tenant route scope does not match the request tenant context.');
        }

        $tenantId = $headerTenantId ?? $routeTenantId;
        $source = $headerTenantId !== null ? 'header' : ($routeTenantId !== null ? 'route' : null);

        $this->tenantContext->initialize($tenantId, $source);
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_context_source', $source);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new LogicException('Tenant context middleware must return an HTTP response.');
        }

        return $response;
    }

    private function resolveRouteTenantId(Request $request): ?string
    {
        $parameterNames = config('medflow.tenancy.route_parameters', ['tenantId', 'tenant']);

        if (! is_array($parameterNames)) {
            return null;
        }

        foreach ($parameterNames as $parameterName) {
            if (! is_string($parameterName)) {
                continue;
            }

            $value = $request->route($parameterName);

            if ($value === null) {
                continue;
            }

            return $this->normalizeTenantId($value, "route parameter [{$parameterName}]");
        }

        return null;
    }

    private function normalizeTenantId(mixed $value, string $source): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidTenantContext("The {$source} tenant identifier must be a string UUID.");
        }

        if (! Str::isUuid($value)) {
            throw new InvalidTenantContext("The {$source} tenant identifier must be a valid UUID.");
        }

        return strtolower($value);
    }
}
