<?php

namespace App\Shared\Infrastructure\Observability\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RequirePrometheusScrapeKey
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = trim(config()->string('operations.metrics.scrape_key', ''));

        if ($configured === '') {
            throw new AccessDeniedHttpException('Prometheus scrape access is not configured.');
        }

        $provided = $request->header('X-Prometheus-Scrape-Key');

        if (! is_string($provided) || trim($provided) === '') {
            $authorization = $request->header('Authorization');
            $provided = is_string($authorization) && str_starts_with($authorization, 'Bearer ')
                ? trim(substr($authorization, 7))
                : null;
        }

        if (! is_string($provided) || ! hash_equals($configured, trim($provided))) {
            throw new AccessDeniedHttpException('The Prometheus scrape key is invalid.');
        }

        /** @var mixed $nextResponse */
        $nextResponse = $next($request);

        if (! $nextResponse instanceof Response) {
            throw new \UnexpectedValueException('Expected a Response instance from the HTTP pipeline.');
        }

        return $nextResponse;
    }
}
