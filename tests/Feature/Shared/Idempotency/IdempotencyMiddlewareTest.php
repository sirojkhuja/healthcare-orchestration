<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Fixtures\Shared\Idempotency\FakeIdempotentCommandAction;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $action = new FakeIdempotentCommandAction;
    $this->app->instance(FakeIdempotentCommandAction::class, $action);

    Route::middleware(['api', 'tenant.require', 'idempotency:appointments.schedule'])
        ->post('/api/v1/_tests/idempotency/appointments', function (Request $request, FakeIdempotentCommandAction $action) {
            return $action($request);
        });
});

it('replays the original response for duplicate idempotent requests', function (): void {
    $user = User::factory()->create();
    $tenantId = (string) Str::uuid();
    $key = (string) Str::uuid();
    $payload = [
        'patient_id' => (string) Str::uuid(),
        'provider_id' => (string) Str::uuid(),
    ];

    $this->actingAs($user);

    $firstResponse = $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', $payload);

    $firstResponse
        ->assertCreated()
        ->assertHeader('Idempotency-Key', $key)
        ->assertJson([
            'sequence' => 1,
            'tenant_id' => $tenantId,
            'payload' => $payload,
        ]);

    $secondResponse = $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', $payload);

    $secondResponse
        ->assertCreated()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertExactJson($firstResponse->json());

    expect(app(FakeIdempotentCommandAction::class)->invocationCount)->toBe(1);
});

it('rejects idempotency key reuse for a different request payload', function (): void {
    $user = User::factory()->create();
    $tenantId = (string) Str::uuid();
    $key = (string) Str::uuid();

    $this->actingAs($user);

    $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', [
        'patient_id' => (string) Str::uuid(),
        'provider_id' => (string) Str::uuid(),
    ])->assertCreated();

    $response = $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', [
        'patient_id' => (string) Str::uuid(),
        'provider_id' => (string) Str::uuid(),
    ]);

    $response
        ->assertStatus(409)
        ->assertJsonPath('code', 'IDEMPOTENCY_REPLAY')
        ->assertJsonPath('details.reason', 'payload_mismatch');

    expect(app(FakeIdempotentCommandAction::class)->invocationCount)->toBe(1);
});

it('isolates idempotent requests by tenant scope', function (): void {
    $user = User::factory()->create();
    $key = (string) Str::uuid();
    $payload = [
        'patient_id' => (string) Str::uuid(),
        'provider_id' => (string) Str::uuid(),
    ];

    $this->actingAs($user);

    $this->withHeaders([
        'X-Tenant-Id' => (string) Str::uuid(),
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', $payload)
        ->assertCreated();

    $this->withHeaders([
        'X-Tenant-Id' => (string) Str::uuid(),
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/_tests/idempotency/appointments', $payload)
        ->assertCreated()
        ->assertHeaderMissing('X-Idempotent-Replay');

    expect(app(FakeIdempotentCommandAction::class)->invocationCount)->toBe(2);
});

it('requires an idempotency key for protected commands', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->withHeader('X-Tenant-Id', (string) Str::uuid())
        ->postJson('/api/v1/_tests/idempotency/appointments', [
            'patient_id' => (string) Str::uuid(),
            'provider_id' => (string) Str::uuid(),
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('details.errors.Idempotency-Key.0', 'The idempotency header is required for this operation.');
});
