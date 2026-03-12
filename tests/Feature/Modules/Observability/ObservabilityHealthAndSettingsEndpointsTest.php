<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/ObservabilityTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('integrations.feature_flags.myid', false);
    config()->set('integrations.feature_flags.eimzo', false);
});

it('returns health runtime feature flag rate limit logging and config views', function (): void {
    $manager = User::factory()->create([
        'email' => 'ops.health.manager@example.test',
        'password' => 'secret-password',
    ]);

    $token = observabilityIssueBearerToken($this, 'ops.health.manager@example.test');
    $tenantId = observabilityCreateTenant($this, $token, 'Observability Tenant')->json('data.id');
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
        ->assertOk()
        ->assertJsonPath('status', 'healthy')
        ->assertJsonPath('summary.failed_jobs', 0);

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/ready')
        ->assertOk()
        ->assertJsonPath('status', 'ready');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/live')
        ->assertOk()
        ->assertJsonPath('status', 'alive');

    $this->withToken($token)
        ->withHeaders($headers)
        ->get('/api/v1/metrics')
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; version=0.0.4; charset=UTF-8')
        ->assertSeeText('medflow_app_info')
        ->assertSeeText('medflow_health_status');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/version')
        ->assertOk()
        ->assertJsonPath('data.version', config('medflow.version'))
        ->assertJsonPath('data.environment', config('app.env'));

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/feature-flags')
        ->assertOk()
        ->assertJsonPath('data.1.key', 'myid')
        ->assertJsonPath('data.1.enabled', false)
        ->assertJsonPath('data.1.source', 'default');

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-feature-flags-1'])
        ->putJson('/api/v1/admin/feature-flags', [
            'flags' => [
                [
                    'key' => 'myid',
                    'enabled' => true,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'feature_flags_updated');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/feature-flags')
        ->assertOk()
        ->assertJsonPath('data.1.key', 'myid')
        ->assertJsonPath('data.1.enabled', true)
        ->assertJsonPath('data.1.source', 'tenant_override');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/integrations/myid')
        ->assertOk()
        ->assertJsonPath('data.integration_key', 'myid')
        ->assertJsonPath('data.available', true);

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/rate-limits')
        ->assertOk()
        ->assertJsonPath('data.0.source', 'default');

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-rate-limits-1'])
        ->putJson('/api/v1/admin/rate-limits', [
            'limits' => [
                [
                    'bucket_key' => 'admin.actions',
                    'requests_per_minute' => 12,
                    'burst' => 6,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'rate_limits_updated');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/rate-limits')
        ->assertOk()
        ->assertJsonPath('data.0.bucket_key', 'admin.actions')
        ->assertJsonPath('data.0.requests_per_minute', 12)
        ->assertJsonPath('data.0.burst', 6)
        ->assertJsonPath('data.0.source', 'tenant_override');

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/logging/pipelines')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.status', 'active');

    $reloadResponse = $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-logging-reload-1'])
        ->postJson('/api/v1/admin/logging:pipeline-reload', [
            'pipelines' => ['app-json'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'logging_pipelines_reloaded')
        ->assertJsonPath('data.0.key', 'app-json');

    expect($reloadResponse->json('data.0.last_reloaded_at'))->not->toBeNull();

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/config')
        ->assertOk()
        ->assertJsonPath('data.service', config('app.name'))
        ->assertJsonPath('data.cache_store', config('cache.default'));

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-config-reload-1'])
        ->postJson('/api/v1/admin/config:reload')
        ->assertOk()
        ->assertJsonPath('status', 'runtime_config_reloaded')
        ->assertJsonPath('data.version', config('medflow.version'));

    expect(AuditEventRecord::query()->where('action', 'admin.feature_flags_updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.rate_limits_updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.logging_pipelines_reloaded')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.runtime_config_reloaded')->exists())->toBeTrue();
});
