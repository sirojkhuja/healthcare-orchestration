<?php

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Events\PermissionProjectionInvalidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Fixtures\IdentityAccess\FakePermissionProjectionRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::store()->flush();

    Route::middleware(['api', 'tenant.require', 'permission:patients.view'])->get('/api/v1/_tests/permissions/patients', function () {
        return response()->json(['status' => 'ok']);
    });
});

it('authorizes routes through the cached permission projection', function (): void {
    $user = User::factory()->create();
    $tenantId = (string) Str::uuid();
    $repository = new FakePermissionProjectionRepository;
    $repository->setPermissions((string) $user->getAuthIdentifier(), $tenantId, ['patients.view']);

    $this->app->instance(PermissionProjectionRepository::class, $repository);
    $this->app->forgetInstance(\App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer::class);
    $this->actingAs($user);

    $firstResponse = $this->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/permissions/patients');

    $firstResponse->assertOk();
    expect($repository->loadCount)->toBe(1);

    $secondResponse = $this->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/permissions/patients');

    $secondResponse->assertOk();
    expect($repository->loadCount)->toBe(1);
});

it('invalidates cached permission projections when the invalidation event is dispatched', function (): void {
    $user = User::factory()->create();
    $tenantId = (string) Str::uuid();
    $userId = (string) $user->getAuthIdentifier();
    $repository = new FakePermissionProjectionRepository;
    $repository->setPermissions($userId, $tenantId, ['patients.view']);

    $this->app->instance(PermissionProjectionRepository::class, $repository);
    $this->app->forgetInstance(\App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer::class);
    $this->actingAs($user);

    $this->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/permissions/patients')
        ->assertOk();

    expect($repository->loadCount)->toBe(1);

    $repository->setPermissions($userId, $tenantId, []);
    Event::dispatch(new PermissionProjectionInvalidated($userId, $tenantId));

    $response = $this->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/permissions/patients');

    $response
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('message', 'You are not allowed to perform this action.');

    expect($repository->loadCount)->toBe(2);
});
