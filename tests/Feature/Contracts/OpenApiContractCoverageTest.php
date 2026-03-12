<?php

use Illuminate\Support\Facades\Route;

const OPENAPI_CONTRACT_HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

it('keeps the bundled openapi operation surface in sync with the live api routes', function (): void {
    $liveOperations = array_keys(openApiContractLiveOperations());
    $documentedOperations = array_keys(openApiContractDocumentedOperations());

    $undocumented = array_values(array_diff($liveOperations, $documentedOperations));
    $orphaned = array_values(array_diff($documentedOperations, $liveOperations));

    openApiContractAssertNoFailures('Live API routes missing from the bundled OpenAPI document', $undocumented);
    openApiContractAssertNoFailures('Bundled OpenAPI operations missing from the live route table', $orphaned);
    expect(true)->toBeTrue();
});

it('documents bearer security for authenticated api routes', function (): void {
    $failures = [];

    foreach (openApiContractLiveOperations() as $operationKey => $route) {
        if (! openApiContractHasMiddleware($route['middleware'], 'auth:api')) {
            continue;
        }

        $operation = openApiContractDocumentedOperations()[$operationKey]['operation'] ?? null;

        if (! is_array($operation) || ($operation['security'] ?? []) === []) {
            $failures[] = $operationKey;
        }
    }

    openApiContractAssertNoFailures('Authenticated routes missing documented security', $failures);
    expect(true)->toBeTrue();
});

it('documents tenant context for tenant required routes', function (): void {
    $failures = [];

    foreach (openApiContractLiveOperations() as $operationKey => $route) {
        if (! openApiContractHasMiddleware($route['middleware'], 'tenant.require')) {
            continue;
        }

        $parameters = openApiContractResolvedParameterNames($operationKey);
        $hasTenantHeader = in_array('X-Tenant-Id', $parameters['header'], true);
        $hasTenantPathParameter = in_array('tenantId', $parameters['path'], true);

        if (! $hasTenantHeader && ! $hasTenantPathParameter) {
            $failures[] = sprintf('%s missing X-Tenant-Id header or tenantId path parameter', $operationKey);

            continue;
        }

        if (! str_contains($route['uri'], '{tenantId}') && ! $hasTenantHeader) {
            $failures[] = sprintf('%s requires tenant header documentation', $operationKey);
        }
    }

    openApiContractAssertNoFailures('Tenant-required routes missing tenant context documentation', $failures);
    expect(true)->toBeTrue();
});

it('documents idempotency keys for routes protected by idempotency middleware', function (): void {
    $failures = [];

    foreach (openApiContractLiveOperations() as $operationKey => $route) {
        if (! openApiContractHasPrefixedMiddleware($route['middleware'], 'idempotency:')) {
            continue;
        }

        $headerNames = openApiContractResolvedParameterNames($operationKey)['header'];

        if (! in_array('Idempotency-Key', $headerNames, true)) {
            $failures[] = $operationKey;
        }
    }

    openApiContractAssertNoFailures('Idempotent routes missing Idempotency-Key documentation', $failures);
    expect(true)->toBeTrue();
});

it('documents request metadata headers on every successful response', function (): void {
    $failures = [];

    foreach (openApiContractDocumentedOperations() as $operationKey => $documentedOperation) {
        foreach ($documentedOperation['operation']['responses'] ?? [] as $statusCode => $response) {
            if (! preg_match('/^[23]\d{2}$/', (string) $statusCode)) {
                continue;
            }

            $headers = array_keys(openApiContractResolveNode($response)['headers'] ?? []);
            $missingHeaders = array_values(array_diff(['X-Request-Id', 'X-Correlation-Id'], $headers));

            if ($missingHeaders !== []) {
                $failures[] = sprintf('%s %s missing %s', $operationKey, $statusCode, implode(', ', $missingHeaders));
            }
        }
    }

    openApiContractAssertNoFailures('Successful responses missing documented request metadata headers', $failures);
    expect(true)->toBeTrue();
});

function openApiContractSpecification(): array
{
    static $specification = null;

    if ($specification === null) {
        $path = base_path('docs/api/openapi/openapi.json');

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Bundled OpenAPI document not found at %s. Run `npm run openapi:build` first.', $path));
        }

        $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Bundled OpenAPI document did not decode into an object.');
        }

        $specification = $decoded;
    }

    return $specification;
}

function openApiContractDocumentedOperations(): array
{
    static $operations = null;

    if ($operations !== null) {
        return $operations;
    }

    $operations = [];

    foreach (openApiContractSpecification()['paths'] ?? [] as $path => $pathItem) {
        if (! is_array($pathItem)) {
            continue;
        }

        foreach (OPENAPI_CONTRACT_HTTP_METHODS as $method) {
            if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                continue;
            }

            $operations[sprintf('%s %s', strtoupper($method), $path)] = [
                'method' => $method,
                'path' => $path,
                'path_item' => $pathItem,
                'operation' => $pathItem[$method],
            ];
        }
    }

    ksort($operations);

    return $operations;
}

function openApiContractLiveOperations(): array
{
    static $operations = null;

    if ($operations !== null) {
        return $operations;
    }

    $operations = [];

    foreach (Route::getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');

        if (! str_starts_with($uri, '/api/v1/')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $operations[sprintf('%s %s', $method, $uri)] = [
                'uri' => $uri,
                'middleware' => $route->gatherMiddleware(),
                'name' => $route->getName(),
            ];
        }
    }

    ksort($operations);

    return $operations;
}

function openApiContractResolvedParameterNames(string $operationKey): array
{
    $documentedOperation = openApiContractDocumentedOperations()[$operationKey] ?? null;

    if (! is_array($documentedOperation)) {
        throw new RuntimeException(sprintf('No documented OpenAPI operation found for %s.', $operationKey));
    }

    $parameters = array_merge(
        $documentedOperation['path_item']['parameters'] ?? [],
        $documentedOperation['operation']['parameters'] ?? [],
    );

    $names = [
        'header' => [],
        'path' => [],
    ];

    foreach ($parameters as $parameter) {
        $resolved = openApiContractResolveNode($parameter);
        $location = $resolved['in'] ?? null;
        $name = $resolved['name'] ?? null;

        if (! is_string($location) || ! is_string($name) || ! array_key_exists($location, $names)) {
            continue;
        }

        $names[$location][] = $name;
    }

    $names['header'] = array_values(array_unique($names['header']));
    $names['path'] = array_values(array_unique($names['path']));

    return $names;
}

function openApiContractResolveNode(array $node): array
{
    $seen = [];

    while (isset($node['$ref'])) {
        $reference = $node['$ref'];

        if (! is_string($reference) || ! str_starts_with($reference, '#/')) {
            throw new RuntimeException(sprintf('Unsupported OpenAPI reference %s.', var_export($reference, true)));
        }

        if (in_array($reference, $seen, true)) {
            throw new RuntimeException(sprintf('Cyclic OpenAPI reference detected for %s.', $reference));
        }

        $seen[] = $reference;
        $node = openApiContractResolvePointer($reference);
    }

    return $node;
}

function openApiContractResolvePointer(string $reference): array
{
    $value = openApiContractSpecification();

    foreach (explode('/', substr($reference, 2)) as $segment) {
        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            throw new RuntimeException(sprintf('OpenAPI reference %s could not be resolved.', $reference));
        }

        $value = $value[$segment];
    }

    if (! is_array($value)) {
        throw new RuntimeException(sprintf('OpenAPI reference %s resolved to a non-object value.', $reference));
    }

    return $value;
}

function openApiContractHasMiddleware(array $middleware, string $name): bool
{
    return in_array($name, $middleware, true);
}

function openApiContractHasPrefixedMiddleware(array $middleware, string $prefix): bool
{
    foreach ($middleware as $entry) {
        if (str_starts_with($entry, $prefix)) {
            return true;
        }
    }

    return false;
}

function openApiContractAssertNoFailures(string $message, array $failures): void
{
    if ($failures === []) {
        return;
    }

    throw new RuntimeException($message.":\n- ".implode("\n- ", $failures));
}
