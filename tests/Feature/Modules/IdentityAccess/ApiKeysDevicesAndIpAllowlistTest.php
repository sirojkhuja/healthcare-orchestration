<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! Route::has('tests.identity-access.api-key-protected')) {
        Route::middleware(['api', 'tenant.require', 'auth:api-key'])
            ->get('/api/v1/_tests/identity-access/api-key-protected', function (Request $request, TenantContext $tenantContext) {
                $user = $request->user();
                $userId = $user?->getAuthIdentifier();

                return response()->json([
                    'user_id' => is_scalar($userId) ? (string) $userId : null,
                    'tenant_id' => $tenantContext->requireTenantId(),
                ]);
            })
            ->name('tests.identity-access.api-key-protected');
    }
});

it('creates, lists, and revokes api keys for the authenticated user', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $createResponse = $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/auth/api-keys', [
            'name' => 'billing-bot',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'api_key_created')
        ->assertJsonPath('api_key.name', 'billing-bot');

    $keyId = $createResponse->json('api_key.id');
    $plainTextKey = $createResponse->json('plaintext_key');

    expect($keyId)->toBeString()->not->toBe('');
    expect($plainTextKey)->toBeString()->toStartWith('mfk_');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->getJson('/api/v1/auth/api-keys')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $keyId)
        ->assertJsonPath('data.0.name', 'billing-bot');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->deleteJson('/api/v1/auth/api-keys/'.$keyId)
        ->assertOk()
        ->assertJsonPath('status', 'api_key_revoked');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->getJson('/api/v1/auth/api-keys')
        ->assertOk()
        ->assertJsonPath('data.0.revoked_at', fn ($value) => is_string($value) && $value !== '');

    expect(AuditEventRecord::query()->where('action', 'auth.api_key_created')->where('object_id', $keyId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'auth.api_key_revoked')->where('object_id', $keyId)->exists())->toBeTrue();
});

it('enforces tenant ip allowlists and revoked api keys on api-key-authenticated routes', function (): void {
    $user = User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $createResponse = $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/auth/api-keys', [
            'name' => 'integration-key',
        ])
        ->assertCreated();

    $plainTextKey = $createResponse->json('plaintext_key');
    $keyId = $createResponse->json('api_key.id');

    $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'X-API-Key' => $plainTextKey,
    ])
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->getJson('/api/v1/_tests/identity-access/api-key-protected')
        ->assertOk()
        ->assertJsonPath('user_id', (string) $user->getAuthIdentifier())
        ->assertJsonPath('tenant_id', Str::lower($tenantId));

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/security/ip-allowlist', [
            'entries' => [
                [
                    'cidr' => '203.0.113.0/24',
                    'label' => 'hq',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ip_allowlist_updated')
        ->assertJsonCount(1, 'data');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/security/ip-allowlist')
        ->assertOk()
        ->assertJsonPath('data.0.cidr', '203.0.113.0/24');

    $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'X-API-Key' => $plainTextKey,
    ])
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->getJson('/api/v1/_tests/identity-access/api-key-protected')
        ->assertStatus(403)
        ->assertJsonPath('code', 'IP_ADDRESS_NOT_ALLOWED');

    $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'X-API-Key' => $plainTextKey,
    ])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->getJson('/api/v1/_tests/identity-access/api-key-protected')
        ->assertOk()
        ->assertJsonPath('tenant_id', Str::lower($tenantId));

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->deleteJson('/api/v1/auth/api-keys/'.$keyId)
        ->assertOk();

    $this->withHeaders([
        'X-Tenant-Id' => $tenantId,
        'X-API-Key' => $plainTextKey,
    ])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->getJson('/api/v1/_tests/identity-access/api-key-protected')
        ->assertStatus(401)
        ->assertJsonPath('code', 'API_KEY_REVOKED');

    expect(AuditEventRecord::query()->where('action', 'security.ip_allowlist_updated')->where('object_id', Str::lower($tenantId))->exists())->toBeTrue();
});

it('registers, updates, lists, and deregisters devices for the authenticated user', function (): void {
    User::factory()->create([
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'doctor@example.test',
        'password' => 'secret-password',
    ])->assertOk();

    $firstRegistration = $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/devices', [
            'installation_id' => 'ios-installation-001',
            'name' => 'Doctor iPhone',
            'platform' => 'ios',
            'push_token' => 'push-token-alpha',
            'app_version' => '1.0.0',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'device_registered')
        ->assertJsonPath('data.installation_id', 'ios-installation-001');

    $deviceId = $firstRegistration->json('data.id');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->postJson('/api/v1/devices', [
            'installation_id' => 'ios-installation-001',
            'name' => 'Doctor iPhone Updated',
            'platform' => 'ios',
            'push_token' => 'push-token-beta',
            'app_version' => '1.1.0',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $deviceId)
        ->assertJsonPath('data.name', 'Doctor iPhone Updated')
        ->assertJsonPath('data.push_token', 'push-token-beta');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->getJson('/api/v1/devices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deviceId)
        ->assertJsonPath('data.0.app_version', '1.1.0');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->deleteJson('/api/v1/devices/'.$deviceId)
        ->assertOk()
        ->assertJsonPath('status', 'device_deregistered');

    $this->withToken($loginResponse->json('tokens.access_token'))
        ->getJson('/api/v1/devices')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(AuditEventRecord::query()->where('action', 'auth.device_registered')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'auth.device_deregistered')->where('object_id', $deviceId)->exists())->toBeTrue();
});
