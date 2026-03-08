<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Http\Middleware;

use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Shared\Application\Contracts\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function __construct(
        private readonly PermissionAuthorizer $permissionAuthorizer,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Authentication is required for this operation.');
        }

        $userId = $user->getAuthIdentifier();

        if (! is_scalar($userId)) {
            throw new AuthorizationException('The authenticated actor could not be resolved.');
        }

        if (! $this->permissionAuthorizer->allows((string) $userId, $this->tenantContext->tenantId(), $permission)) {
            throw new AuthorizationException('You are not allowed to perform this action.');
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new LogicException('Permission middleware must return an HTTP response.');
        }

        return $response;
    }
}
