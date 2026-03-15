<?php

use App\Modules\IdentityAccess\Infrastructure\Authorization\Http\Middleware\RequirePermission;
use App\Shared\Infrastructure\Context\Http\Middleware\ResolveRequestMetadata;
use App\Shared\Infrastructure\Idempotency\Http\Middleware\RequireIdempotencyKey;
use App\Shared\Infrastructure\Observability\Http\Middleware\ObserveHttpRequests;
use App\Shared\Infrastructure\Presentation\ApiErrorResponseFactory;
use App\Shared\Infrastructure\Tenancy\Http\Middleware\RequireTenantContext;
use App\Shared\Infrastructure\Tenancy\Http\Middleware\ResolveTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => '/');
        $middleware->append(ResolveRequestMetadata::class);
        $middleware->appendToGroup('api', ResolveTenantContext::class);
        $middleware->appendToGroup('api', ObserveHttpRequests::class);
        $middleware->alias([
            'idempotency' => RequireIdempotencyKey::class,
            'permission' => RequirePermission::class,
            'tenant.require' => RequireTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $exceptions->render(fn (\Throwable $exception, Request $request) => app(ApiErrorResponseFactory::class)->make($exception, $request));
    })->create();
