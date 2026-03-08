<?php

namespace App\Shared\Infrastructure\Presentation;

use App\Modules\IdentityAccess\Application\Exceptions\MfaChallengeRequiredException;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\ApiError;
use App\Shared\Application\Data\RequestMetadata;
use App\Shared\Application\Exceptions\IdempotencyReplayException;
use App\Shared\Application\Exceptions\InvalidTenantContext;
use App\Shared\Application\Exceptions\MissingTenantContext;
use App\Shared\Application\Exceptions\TenantScopeViolation;
use App\Shared\Infrastructure\Context\RequestMetadataHeaderResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiErrorResponseFactory
{
    public function __construct(
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly RequestMetadataHeaderResolver $headerResolver,
    ) {}

    public function make(Throwable $throwable, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        [$status, $code, $message, $details] = $this->map($throwable);
        $metadata = $this->resolveRequestMetadata();
        $error = new ApiError(
            code: $code,
            message: $message,
            details: $details,
            traceId: $metadata->requestId,
            correlationId: $metadata->correlationId,
        );

        $response = response()->json($error->toArray(), $status);

        foreach ($this->headerResolver->resolve() as $contextKey => $headerName) {
            $response->headers->set($headerName, $metadata->toArray()[$contextKey]);
        }

        return $response;
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>}
     */
    private function map(Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof ValidationException => [
                422,
                'VALIDATION_FAILED',
                'The request payload is invalid.',
                ['errors' => $throwable->errors()],
            ],
            $throwable instanceof MfaChallengeRequiredException => [
                401,
                'MFA_REQUIRED',
                'Multi-factor authentication is required to complete login.',
                [
                    'challenge_id' => $throwable->challengeId,
                    'expires_at' => $throwable->expiresAt->format(DATE_ATOM),
                ],
            ],
            $throwable instanceof AuthenticationException => [
                401,
                'UNAUTHENTICATED',
                'Authentication is required for this operation.',
                [],
            ],
            $throwable instanceof AuthorizationException, $throwable instanceof AccessDeniedHttpException => [
                403,
                'FORBIDDEN',
                'You are not allowed to perform this action.',
                [],
            ],
            $throwable instanceof MissingTenantContext, $throwable instanceof InvalidTenantContext => [
                400,
                'TENANT_CONTEXT_REQUIRED',
                $throwable->getMessage(),
                [],
            ],
            $throwable instanceof TenantScopeViolation => [
                403,
                'TENANT_SCOPE_VIOLATION',
                $throwable->getMessage(),
                [],
            ],
            $throwable instanceof IdempotencyReplayException => [
                409,
                'IDEMPOTENCY_REPLAY',
                $throwable->getMessage(),
                $throwable->details,
            ],
            $throwable instanceof ModelNotFoundException, $throwable instanceof NotFoundHttpException => [
                404,
                'RESOURCE_NOT_FOUND',
                'The requested resource does not exist in the current tenant scope.',
                [],
            ],
            $throwable instanceof ConflictHttpException => [
                409,
                'CONFLICT',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'The request conflicts with the current system state.',
                [],
            ],
            $throwable instanceof TooManyRequestsHttpException => [
                429,
                'RATE_LIMITED',
                'Rate limit exceeded.',
                [],
            ],
            default => [
                500,
                'INTERNAL_ERROR',
                'An unexpected error occurred.',
                [],
            ],
        };
    }

    private function resolveRequestMetadata(): RequestMetadata
    {
        if ($this->requestMetadataContext->hasCurrent()) {
            return $this->requestMetadataContext->current();
        }

        $requestId = (string) Str::uuid();

        return new RequestMetadata(
            requestId: $requestId,
            correlationId: $requestId,
            causationId: $requestId,
        );
    }
}
