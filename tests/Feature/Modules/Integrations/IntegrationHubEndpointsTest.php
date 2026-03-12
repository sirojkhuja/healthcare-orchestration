<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('integrations.feature_flags.myid', true);
    config()->set('integrations.feature_flags.eimzo', false);
    config()->set('app.url', 'https://medflow.example');
});

it('manages integration registry credentials health webhooks logs and tokens', function (): void {
    $manager = User::factory()->create([
        'email' => 'integrations.hub.manager@openai.com',
        'password' => 'secret-password',
    ]);

    $token = treatmentIssueBearerToken($this, 'integrations.hub.manager@openai.com');
    $tenantId = treatmentCreateTenant($this, $token, 'Integrations Hub Tenant')->json('data.id');
    treatmentGrantPermissions($manager, $tenantId, ['integrations.view', 'integrations.manage']);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations?category=notifications')
        ->assertOk()
        ->assertJsonPath('data.0.category', 'notifications');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram')
        ->assertOk()
        ->assertJsonPath('data.integration_key', 'telegram')
        ->assertJsonPath('data.health.status', 'failing');

    $telegramCredentialUpdate = [
        'values' => [
            'api_base_url' => 'https://api.telegram.org',
            'bot_token' => 'telegram-bot-1234',
            'webhook_secret' => 'telegram-secret-5678',
        ],
    ];

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-credentials-1',
        ])
        ->putJson('/api/v1/integrations/telegram/credentials', $telegramCredentialUpdate)
        ->assertOk()
        ->assertJsonPath('status', 'integration_credentials_updated')
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.values.bot_token', '****1234')
        ->assertJsonPath('data.values.api_base_url', 'https://api.telegram.org');

    $storedTelegramCredentials = (string) DB::table('integration_credentials')
        ->where('tenant_id', $tenantId)
        ->where('integration_key', 'telegram')
        ->value('credential_payload');

    expect($storedTelegramCredentials)->not->toContain('telegram-bot-1234');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'degraded');

    $webhookResponse = $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-webhook-1',
        ])
        ->postJson('/api/v1/integrations/telegram/webhooks', [
            'name' => 'primary',
            'metadata' => [
                'scope' => 'support',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'integration_webhook_created')
        ->assertJsonPath('data.endpoint_url', 'https://medflow.example/api/v1/webhooks/telegram')
        ->assertJsonPath('data.rotate_supported', true);

    $webhookId = $webhookResponse->json('data.id');
    $createdWebhookSecret = (string) $webhookResponse->json('data.secret');

    expect($createdWebhookSecret)->not->toBe('');
    expect((string) DB::table('integration_webhooks')->where('id', $webhookId)->value('secret'))
        ->not->toContain($createdWebhookSecret);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram/webhooks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.secret')
        ->assertJsonPath('data.0.name', 'primary');

    $rotatedWebhookResponse = $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-webhook-rotate-1',
        ])
        ->postJson('/api/v1/integrations/telegram/webhooks/'.$webhookId.':rotate-secret')
        ->assertOk()
        ->assertJsonPath('status', 'integration_webhook_secret_rotated')
        ->assertJsonPath('data.id', $webhookId);

    expect((string) $rotatedWebhookResponse->json('data.secret'))->not->toBe($createdWebhookSecret);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'healthy');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-test-1',
        ])
        ->postJson('/api/v1/integrations/telegram:test-connection')
        ->assertOk()
        ->assertJsonPath('status', 'integration_connection_tested')
        ->assertJsonPath('data.status', 'healthy');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram/logs?event=integration.connection_tested')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'integration.connection_tested');

    $myIdCredentialUpdate = [
        'values' => [
            'client_id' => 'myid-client',
            'client_secret' => 'myid-secret',
            'access_token' => 'myid-access-token',
            'refresh_token' => 'myid-refresh-token',
            'access_token_expires_at' => now()->addHour()->toIso8601String(),
            'refresh_token_expires_at' => now()->addDay()->toIso8601String(),
            'token_type' => 'Bearer',
            'scopes' => 'identity profile',
        ],
    ];

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-myid-credentials-1',
        ])
        ->putJson('/api/v1/integrations/myid/credentials', $myIdCredentialUpdate)
        ->assertOk()
        ->assertJsonPath('data.values.client_secret', '****cret');

    expect((string) DB::table('integration_tokens')
        ->where('tenant_id', $tenantId)
        ->where('integration_key', 'myid')
        ->value('access_token'))->not->toContain('myid-access-token');

    $tokenResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/myid/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.access_token_preview', '****oken');

    $tokenId = $tokenResponse->json('data.0.id');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-myid-token-refresh-1',
        ])
        ->postJson('/api/v1/integrations/myid/tokens:refresh', [
            'token_id' => $tokenId,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'integration_token_refreshed')
        ->assertJsonPath('data.id', $tokenId)
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-myid-token-revoke-1',
        ])
        ->deleteJson('/api/v1/integrations/myid/tokens/'.$tokenId)
        ->assertOk()
        ->assertJsonPath('status', 'integration_token_revoked')
        ->assertJsonPath('data.status', 'revoked');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-myid-credentials-delete-1',
        ])
        ->deleteJson('/api/v1/integrations/myid/credentials')
        ->assertOk()
        ->assertJsonPath('status', 'integration_credentials_deleted')
        ->assertJsonPath('data.configured', false);

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-disable-1',
        ])
        ->postJson('/api/v1/integrations/telegram:disable')
        ->assertOk()
        ->assertJsonPath('status', 'integration_disabled')
        ->assertJsonPath('data.enabled', false);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/telegram/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-telegram-enable-1',
        ])
        ->postJson('/api/v1/integrations/telegram:enable')
        ->assertOk()
        ->assertJsonPath('status', 'integration_enabled')
        ->assertJsonPath('data.enabled', true);

    expect(AuditEventRecord::query()->where('action', 'integrations.credentials_updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.connection_tested')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.webhook_secret_rotated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.token_refreshed')->exists())->toBeTrue();
});

it('enforces integration permissions and feature flag rules', function (): void {
    $manager = User::factory()->create([
        'email' => 'integrations.hub.guard.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'integrations.hub.guard.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = treatmentIssueBearerToken($this, 'integrations.hub.guard.manager@openai.com');
    $viewerToken = treatmentIssueBearerToken($this, 'integrations.hub.guard.viewer@openai.com');
    $tenantId = treatmentCreateTenant($this, $managerToken, 'Integrations Guard Tenant')->json('data.id');

    treatmentGrantPermissions($manager, $tenantId, ['integrations.manage']);
    treatmentGrantPermissions($viewer, $tenantId, ['integrations.view']);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-guard-update-1',
        ])
        ->putJson('/api/v1/integrations/telegram/credentials', [
            'values' => [
                'bot_token' => 'blocked',
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/integrations/eimzo')
        ->assertOk()
        ->assertJsonPath('data.available', false);

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-eimzo-enable-1',
        ])
        ->postJson('/api/v1/integrations/eimzo:enable')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-payme-webhook-1',
        ])
        ->postJson('/api/v1/integrations/payme/webhooks', [
            'name' => 'primary',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});
