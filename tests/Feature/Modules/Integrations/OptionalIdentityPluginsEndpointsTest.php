<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('integrations.feature_flags.myid', true);
    config()->set('integrations.feature_flags.eimzo', true);
    config()->set('app.url', 'https://medflow.example');
});

it('processes optional myid and eimzo initiation and webhook flows', function (): void {
    [, $token, $tenantId] = optionalPluginManagerContext($this, 'success');
    $myIdWebhook = optionalPluginReadyIntegration($this, $token, $tenantId, 'myid', [
        'client_id' => 'myid-client',
        'client_secret' => 'myid-secret',
    ], 'optional-myid');
    $eImzoWebhook = optionalPluginReadyIntegration($this, $token, $tenantId, 'eimzo', [
        'client_id' => 'eimzo-client',
        'client_secret' => 'eimzo-secret',
    ], 'optional-eimzo');

    $verificationResponse = $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'optional-myid-verify-1',
        ])
        ->postJson('/api/v1/integrations/myid:verify', [
            'external_reference' => 'patient-kyc-1',
            'subject' => [
                'first_name' => 'Amina',
                'document_number' => 'AA1234567',
            ],
            'metadata' => [
                'channel' => 'front-desk',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'myid_verification_created')
        ->assertJsonPath('data.integration_key', 'myid')
        ->assertJsonPath('data.status', 'pending');

    $verificationId = (string) $verificationResponse->json('data.verification_id');
    $myIdProviderReference = (string) $verificationResponse->json('data.provider_reference');
    $this->assertDatabaseHas('myid_verifications', [
        'id' => $verificationId,
        'tenant_id' => $tenantId,
        'webhook_id' => $myIdWebhook['id'],
        'provider_reference' => $myIdProviderReference,
        'status' => 'pending',
    ]);

    $myIdWebhookPayload = [
        'webhook_id' => $myIdWebhook['id'],
        'delivery_id' => 'myid-delivery-1',
        'provider_reference' => $myIdProviderReference,
        'status' => 'verified',
        'result_payload' => [
            'match_score' => 98,
            'document_status' => 'valid',
        ],
        'metadata' => [
            'provider' => 'sandbox',
        ],
    ];

    $this->withHeader('X-Integration-Webhook-Secret', $myIdWebhook['secret'])
        ->postJson('/api/v1/webhooks/myid', $myIdWebhookPayload)
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->withHeader('X-Integration-Webhook-Secret', $myIdWebhook['secret'])
        ->postJson('/api/v1/webhooks/myid', $myIdWebhookPayload)
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->assertDatabaseHas('myid_verifications', [
        'id' => $verificationId,
        'status' => 'verified',
    ]);

    expect(DB::table('integration_plugin_webhook_deliveries')
        ->where('integration_key', 'myid')
        ->where('webhook_id', $myIdWebhook['id'])
        ->where('delivery_id', 'myid-delivery-1')
        ->count())->toBe(1);

    expect(json_decode((string) DB::table('myid_verifications')
        ->where('id', $verificationId)
        ->value('result_payload'), true))->toMatchArray([
            'match_score' => 98,
            'document_status' => 'valid',
        ]);

    $signRequestResponse = $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'optional-eimzo-sign-1',
        ])
        ->postJson('/api/v1/integrations/eimzo:sign', [
            'external_reference' => 'consent-form-77',
            'document_hash' => 'sha256:3b2d43d8fd6b1cd27be1f7adf8754b0a',
            'document_name' => 'patient-consent.pdf',
            'signer' => [
                'pinfl' => '12345678901234',
                'full_name' => 'Aziz Karimov',
            ],
            'metadata' => [
                'document_type' => 'consent',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'eimzo_sign_request_created')
        ->assertJsonPath('data.integration_key', 'eimzo')
        ->assertJsonPath('data.status', 'pending');

    $signRequestId = (string) $signRequestResponse->json('data.sign_request_id');
    $eImzoProviderReference = (string) $signRequestResponse->json('data.provider_reference');

    $this->assertDatabaseHas('eimzo_sign_requests', [
        'id' => $signRequestId,
        'tenant_id' => $tenantId,
        'webhook_id' => $eImzoWebhook['id'],
        'provider_reference' => $eImzoProviderReference,
        'status' => 'pending',
    ]);

    $this->withHeader('X-Integration-Webhook-Secret', $eImzoWebhook['secret'])
        ->postJson('/api/v1/webhooks/eimzo', [
            'webhook_id' => $eImzoWebhook['id'],
            'delivery_id' => 'eimzo-delivery-1',
            'provider_reference' => $eImzoProviderReference,
            'status' => 'signed',
            'signature_payload' => [
                'signature_serial' => 'sig-001',
                'signed_at' => '2026-03-12T10:30:00+05:00',
            ],
        ])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->assertDatabaseHas('eimzo_sign_requests', [
        'id' => $signRequestId,
        'status' => 'signed',
    ]);

    expect((string) DB::table('integration_plugin_webhook_deliveries')
        ->where('integration_key', 'eimzo')
        ->where('webhook_id', $eImzoWebhook['id'])
        ->where('delivery_id', 'eimzo-delivery-1')
        ->value('outcome'))->toBe('processed');
    expect((string) DB::table('integration_plugin_webhook_deliveries')
        ->where('integration_key', 'myid')
        ->where('webhook_id', $myIdWebhook['id'])
        ->where('delivery_id', 'myid-delivery-1')
        ->value('secret_hash'))->toBe(hash('sha256', $myIdWebhook['secret']));

    expect(AuditEventRecord::query()->where('action', 'integrations.myid.verification_requested')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.myid.webhook_processed')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.eimzo.sign_requested')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'integrations.eimzo.webhook_processed')->exists())->toBeTrue();
    expect(DB::table('integration_logs')->where('integration_key', 'myid')->where('event', 'myid.webhook_processed')->exists())->toBeTrue();
    expect(DB::table('integration_logs')->where('integration_key', 'eimzo')->where('event', 'eimzo.webhook_processed')->exists())->toBeTrue();
});

it('rejects optional plugin initiation when the feature flag is disabled', function (): void {
    config()->set('integrations.feature_flags.myid', false);

    [, $token, $tenantId] = optionalPluginManagerContext($this, 'feature-flag');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'optional-myid-flag-off-1',
        ])
        ->postJson('/api/v1/integrations/myid:verify', [
            'external_reference' => 'blocked-kyc-1',
            'subject' => [
                'document_number' => 'AA0000001',
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

function optionalPluginManagerContext($testCase, string $suffix): array
{
    $manager = User::factory()->create([
        'email' => sprintf('optional.plugins.%s@openai.com', $suffix),
        'password' => 'secret-password',
    ]);

    $token = treatmentIssueBearerToken($testCase, sprintf('optional.plugins.%s@openai.com', $suffix));
    $tenantId = treatmentCreateTenant($testCase, $token, 'Optional Plugin Tenant '.$suffix)->json('data.id');
    treatmentGrantPermissions($manager, $tenantId, ['integrations.manage']);

    return [$manager, $token, $tenantId];
}

function optionalPluginReadyIntegration(
    $testCase,
    string $token,
    string $tenantId,
    string $integrationKey,
    array $credentialValues,
    string $keyPrefix,
): array {
    $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $keyPrefix.'-enable-1',
        ])
        ->postJson('/api/v1/integrations/'.$integrationKey.':enable')
        ->assertOk()
        ->assertJsonPath('data.enabled', true);

    $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $keyPrefix.'-credentials-1',
        ])
        ->putJson('/api/v1/integrations/'.$integrationKey.'/credentials', [
            'values' => $credentialValues,
        ])
        ->assertOk()
        ->assertJsonPath('data.configured', true);

    $response = $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $keyPrefix.'-webhook-1',
        ])
        ->postJson('/api/v1/integrations/'.$integrationKey.'/webhooks', [
            'name' => strtoupper($integrationKey).' Primary',
        ])
        ->assertCreated()
        ->assertJsonPath('data.endpoint_url', 'https://medflow.example/api/v1/webhooks/'.$integrationKey)
        ->assertJsonPath('data.rotate_supported', true);

    return [
        'id' => (string) $response->json('data.id'),
        'secret' => (string) $response->json('data.secret'),
    ];
}
