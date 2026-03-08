<?php

namespace App\Shared\Infrastructure\Tenancy\Http\Middleware;

use App\Shared\Application\Contracts\TenantContext;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->requireTenantId();

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new LogicException('Tenant requirement middleware must return an HTTP response.');
        }

        return $response;
    }
}
