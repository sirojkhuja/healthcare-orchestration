<?php

namespace App\Shared\Infrastructure\Idempotency\Http\Middleware;

use App\Shared\Application\Contracts\IdempotencyStore;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\IdempotencyScope;
use App\Shared\Application\Data\StoredHttpResponse;
use App\Shared\Infrastructure\Context\RequestMetadataHeaderResolver;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequireIdempotencyKey
{
    public function __construct(
        private readonly IdempotencyStore $idempotencyStore,
        private readonly TenantContext $tenantContext,
        private readonly RequestMetadataHeaderResolver $requestMetadataHeaderResolver,
    ) {}

    public function handle(Request $request, Closure $next, string $operation): Response
    {
        $key = $this->resolveIdempotencyKey($request);
        $decision = $this->idempotencyStore->acquire(
            scope: new IdempotencyScope(
                operation: $operation,
                tenantId: $this->tenantContext->tenantId(),
                actorId: $this->resolveActorId(),
            ),
            key: $key,
            fingerprint: $this->fingerprint($request, $operation),
            expiresAt: CarbonImmutable::now()->addHours($this->retentionHours()),
        );

        if ($decision->isReplay()) {
            if ($decision->storedResponse === null) {
                throw new LogicException('Replay decisions must include a stored response.');
            }

            return $this->replayResponse($decision->storedResponse, $key);
        }

        if ($decision->recordId === null) {
            throw new LogicException('Execution decisions must include a storage record identifier.');
        }

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $this->idempotencyStore->release($decision->recordId);

            throw $throwable;
        }

        if (! $response instanceof Response) {
            $this->idempotencyStore->release($decision->recordId);

            throw new LogicException('Idempotency middleware must return an HTTP response.');
        }

        $this->attachIdempotencyHeaders($response, $key, false);

        if ($response->getStatusCode() >= 500) {
            $this->idempotencyStore->release($decision->recordId);

            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            $this->idempotencyStore->release($decision->recordId);

            throw new LogicException('Idempotency middleware supports non-streamed HTTP responses only.');
        }

        $this->idempotencyStore->complete(
            recordId: $decision->recordId,
            response: new StoredHttpResponse(
                status: $response->getStatusCode(),
                body: $content,
                headers: $this->storableHeaders($response),
            ),
        );

        return $response;
    }

    private function normalize(mixed $value): mixed
    {
        return is_array($value) ? $this->normalizeArray($value) : $value;
    }

    private function resolveActorId(): ?string
    {
        $actor = Auth::user();

        if ($actor === null) {
            return null;
        }

        $actorId = $actor->getAuthIdentifier();

        if (! is_scalar($actorId)) {
            throw new LogicException('The authenticated actor identifier must be scalar for idempotency scope resolution.');
        }

        return (string) $actorId;
    }

    private function resolveIdempotencyKey(Request $request): string
    {
        $headerName = $this->headerName();
        $value = $request->header($headerName);

        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([
                $headerName => ['The idempotency header is required for this operation.'],
            ]);
        }

        $trimmed = trim($value);

        if (strlen($trimmed) > 255) {
            throw ValidationException::withMessages([
                $headerName => ['The idempotency header may not be greater than 255 characters.'],
            ]);
        }

        return $trimmed;
    }

    private function replayResponse(StoredHttpResponse $storedResponse, string $key): Response
    {
        $response = new Response($storedResponse->body, $storedResponse->status);

        foreach ($storedResponse->headers as $headerName => $values) {
            $response->headers->set($headerName, $values);
        }

        $this->attachIdempotencyHeaders($response, $key, true);

        return $response;
    }

    private function attachIdempotencyHeaders(Response $response, string $key, bool $isReplay): void
    {
        $response->headers->set($this->headerName(), $key);

        if ($isReplay) {
            $response->headers->set($this->replayHeader(), 'true');
        }
    }

    private function fingerprint(Request $request, string $operation): string
    {
        $routeSignature = $request->route()->uri();
        $payload = [
            'operation' => $operation,
            'method' => strtoupper($request->method()),
            'route' => $routeSignature,
            'query' => $this->normalize($request->query()),
            'body' => $this->normalize($request->request->all()),
            'json' => $this->normalize($request->json()->all()),
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash('sha256', $encoded);
    }

    /**
     * @return array<string, list<string>>
     */
    private function storableHeaders(Response $response): array
    {
        $excluded = array_map('strtolower', [
            ...array_values($this->requestMetadataHeaderResolver->resolve()),
            $this->headerName(),
            $this->replayHeader(),
        ]);

        /** @var array<string, list<string>> $headers */
        $headers = array_filter(
            $response->headers->allPreserveCaseWithoutCookies(),
            static fn ($headerName): bool => is_string($headerName) && ! in_array(strtolower($headerName), $excluded, true),
            ARRAY_FILTER_USE_KEY,
        );

        return $headers;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     *
     * @psalm-suppress MixedAssignment
     */
    private function normalizeArray(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->normalizeArray($item) : $item,
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $nestedValue) {
            $value[$key] = is_array($nestedValue) ? $this->normalizeArray($nestedValue) : $nestedValue;
        }

        return $value;
    }

    private function headerName(): string
    {
        /** @psalm-suppress MixedAssignment */
        $configuredHeader = config('medflow.idempotency.header', 'Idempotency-Key');

        return is_string($configuredHeader) && $configuredHeader !== '' ? $configuredHeader : 'Idempotency-Key';
    }

    private function replayHeader(): string
    {
        /** @psalm-suppress MixedAssignment */
        $configuredHeader = config('medflow.idempotency.replay_header', 'X-Idempotent-Replay');

        return is_string($configuredHeader) && $configuredHeader !== '' ? $configuredHeader : 'X-Idempotent-Replay';
    }

    private function retentionHours(): int
    {
        /** @psalm-suppress MixedAssignment */
        $configuredHours = config('medflow.idempotency.retention_hours', 24);

        if (is_numeric($configuredHours) && (int) $configuredHours > 0) {
            return (int) $configuredHours;
        }

        return 24;
    }
}
