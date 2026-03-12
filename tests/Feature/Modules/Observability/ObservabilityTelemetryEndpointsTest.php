<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/ObservabilityTestSupport.php';

uses(RefreshDatabase::class);

it('exposes expanded telemetry metrics and internal scrape access', function (): void {
    $manager = User::factory()->create([
        'email' => 'ops.telemetry.manager@example.test',
        'password' => 'secret-password',
    ]);

    $token = observabilityIssueBearerToken($this, 'ops.telemetry.manager@example.test');
    $tenantId = observabilityCreateTenant($this, $token, 'Telemetry Tenant')->json('data.id');
    observabilityGrantPermissions($manager, $tenantId, [
        'admin.view',
        'admin.manage',
        'integrations.view',
    ]);

    $headers = [
        'X-Tenant-Id' => $tenantId,
    ];

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/health')
        ->assertOk();

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/feature-flags')
        ->assertOk();

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/feature-flags')
        ->assertOk();

    $this->withHeaders([
        'X-Telegram-Bot-Api-Secret-Token' => 'invalid-secret',
    ])->postJson('/api/v1/webhooks/telegram', [
        'update_id' => 'telegram-update-1',
        'message' => [
            'message_id' => 101,
            'text' => 'hello',
            'chat' => [
                'id' => 'support-chat',
            ],
        ],
    ])->assertUnauthorized();

    $this->withToken($token)
        ->withHeaders($headers)
        ->get('/api/v1/metrics')
        ->assertOk()
        ->assertSeeText('medflow_http_requests_total')
        ->assertSeeText('medflow_http_request_duration_seconds_bucket')
        ->assertSeeText('medflow_cache_hits_total')
        ->assertSeeText('medflow_cache_misses_total')
        ->assertSeeText('medflow_cache_hit_ratio')
        ->assertSeeText('medflow_webhook_verification_failures_total');

    $this->withHeader('Authorization', 'Bearer testing-prometheus-scrape')
        ->get('/internal/metrics')
        ->assertOk()
        ->assertSeeText('medflow_app_info');
});

it('rejects internal scrape requests without the configured key', function (): void {
    $this->get('/internal/metrics')->assertForbidden();
});
