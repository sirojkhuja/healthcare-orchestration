<?php

use App\Shared\Application\Contracts\IdempotencyStore;
use App\Shared\Application\Data\IdempotencyScope;
use App\Shared\Application\Exceptions\IdempotencyReplayException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('rejects a duplicate idempotent request while the original request is still processing', function (): void {
    $store = app(IdempotencyStore::class);
    $key = (string) Str::uuid();
    $scope = new IdempotencyScope(
        operation: 'appointments.schedule',
        tenantId: (string) Str::uuid(),
        actorId: (string) Str::uuid(),
    );

    $decision = $store->acquire(
        scope: $scope,
        key: $key,
        fingerprint: hash('sha256', 'initial'),
        expiresAt: CarbonImmutable::now()->addDay(),
    );

    expect($decision->shouldExecute)->toBeTrue();
    expect($decision->recordId)->not->toBeNull();

    expect(fn () => $store->acquire(
        scope: $scope,
        key: $key,
        fingerprint: hash('sha256', 'initial'),
        expiresAt: CarbonImmutable::now()->addDay(),
    ))->toThrow(IdempotencyReplayException::class);

    $store->release($decision->recordId);
});
