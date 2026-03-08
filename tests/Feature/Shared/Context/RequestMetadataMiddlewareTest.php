<?php

use Illuminate\Support\Str;

it('generates stable request metadata headers when clients do not supply them', function (): void {
    $response = $this->get('/api/v1/ping');

    $requestId = $response->headers->get('X-Request-Id');
    $correlationId = $response->headers->get('X-Correlation-Id');
    $causationId = $response->headers->get('X-Causation-Id');

    expect(is_string($requestId) && Str::isUuid($requestId))->toBeTrue();
    expect($correlationId)->toBe($requestId);
    expect($causationId)->toBe($requestId);
});

it('preserves inbound request metadata headers when they are valid uuids', function (): void {
    $requestId = (string) Str::uuid();
    $correlationId = (string) Str::uuid();
    $causationId = (string) Str::uuid();

    $response = $this->withHeaders([
        'X-Request-Id' => strtoupper($requestId),
        'X-Correlation-Id' => strtoupper($correlationId),
        'X-Causation-Id' => strtoupper($causationId),
    ])->get('/api/v1/ping');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id', strtolower($requestId))
        ->assertHeader('X-Correlation-Id', strtolower($correlationId))
        ->assertHeader('X-Causation-Id', strtolower($causationId));
});
