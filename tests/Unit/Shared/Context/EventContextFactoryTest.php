<?php

use App\Shared\Application\Contracts\EventContextFactory;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\RequestMetadata;
use Illuminate\Support\Str;

test('it builds event context from the current request metadata and tenant context', function (): void {
    $tenantId = (string) Str::uuid();
    $requestId = (string) Str::uuid();
    $correlationId = (string) Str::uuid();
    $causationId = (string) Str::uuid();

    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: $requestId,
        correlationId: $correlationId,
        causationId: $causationId,
    ));
    app(TenantContext::class)->initialize($tenantId, 'test');

    $eventContext = app(EventContextFactory::class)->make();

    expect($eventContext)->toBe([
        'request_id' => $requestId,
        'correlation_id' => $correlationId,
        'causation_id' => $requestId,
        'tenant_id' => $tenantId,
    ]);
});

test('it allows an explicit causation identifier for downstream messages', function (): void {
    $requestId = (string) Str::uuid();
    $correlationId = (string) Str::uuid();
    $causationId = (string) Str::uuid();

    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: $requestId,
        correlationId: $correlationId,
        causationId: (string) Str::uuid(),
    ));

    $eventContext = app(EventContextFactory::class)->make($causationId);

    expect($eventContext['request_id'])->toBe($requestId);
    expect($eventContext['correlation_id'])->toBe($correlationId);
    expect($eventContext['causation_id'])->toBe($causationId);
});
