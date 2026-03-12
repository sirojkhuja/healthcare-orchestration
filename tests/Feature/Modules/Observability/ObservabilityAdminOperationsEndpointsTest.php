<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Shared\Application\Contracts\KafkaProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\Messaging\FakeKafkaProducer;

require_once __DIR__.'/ObservabilityTestSupport.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->instance(KafkaProducer::class, new FakeKafkaProducer);
});

it('operates cache failed jobs kafka receipts and outbox admin flows', function (): void {
    $manager = User::factory()->create([
        'email' => 'ops.admin.manager@example.test',
        'password' => 'secret-password',
    ]);

    $token = observabilityIssueBearerToken($this, 'ops.admin.manager@example.test');
    $tenantId = observabilityCreateTenant($this, $token, 'Admin Ops Tenant')->json('data.id');
    observabilityGrantPermissions($manager, $tenantId, ['admin.view', 'admin.manage']);

    $headers = [
        'X-Tenant-Id' => $tenantId,
    ];

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'Example Failed Job'], JSON_THROW_ON_ERROR),
        'exception' => "RuntimeException: Expected failure\n#0 /app/test.php",
        'failed_at' => now(),
    ]);

    DB::table('kafka_consumer_receipts')->insert([
        [
            'id' => (string) Str::uuid(),
            'consumer_name' => 'notification-sms',
            'message_id' => 'event-1',
            'topic' => 'medflow.notifications.v1',
            'partition' => 0,
            'processed_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(2),
        ],
        [
            'id' => (string) Str::uuid(),
            'consumer_name' => 'notification-sms',
            'message_id' => 'event-2',
            'topic' => 'medflow.notifications.v1',
            'partition' => 0,
            'processed_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
        ],
    ]);

    $failedOutboxId = (string) Str::uuid();
    $pendingOutboxId = (string) Str::uuid();

    DB::table('outbox_messages')->insert([
        [
            'id' => $failedOutboxId,
            'event_id' => (string) Str::uuid(),
            'event_type' => 'notification.failed',
            'topic' => 'medflow.notifications.v1',
            'tenant_id' => $tenantId,
            'request_id' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'causation_id' => (string) Str::uuid(),
            'partition_key' => 'patient-1',
            'headers' => json_encode([], JSON_THROW_ON_ERROR),
            'payload' => json_encode(['notification_id' => 'n1'], JSON_THROW_ON_ERROR),
            'status' => 'failed',
            'attempts' => 2,
            'next_attempt_at' => now()->subMinute(),
            'claimed_at' => null,
            'delivered_at' => null,
            'last_error' => 'broker unavailable',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ],
        [
            'id' => $pendingOutboxId,
            'event_id' => (string) Str::uuid(),
            'event_type' => 'notification.queued',
            'topic' => 'medflow.notifications.v1',
            'tenant_id' => $tenantId,
            'request_id' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'causation_id' => (string) Str::uuid(),
            'partition_key' => 'patient-2',
            'headers' => json_encode([], JSON_THROW_ON_ERROR),
            'payload' => json_encode(['notification_id' => 'n2'], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => null,
            'claimed_at' => null,
            'delivered_at' => null,
            'last_error' => null,
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ],
    ]);

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/jobs')
        ->assertOk()
        ->assertJsonPath('data.summary.failed_jobs', 1)
        ->assertJsonPath('data.items.0.display_name', 'Example Failed Job');

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-job-retry-1'])
        ->postJson('/api/v1/admin/jobs/1:retry')
        ->assertOk()
        ->assertJsonPath('status', 'job_retried')
        ->assertJsonPath('data.job.id', '1');

    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(DB::table('jobs')->count())->toBe(1);

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/kafka/lag')
        ->assertOk()
        ->assertJsonPath('data.consumer_group', config('medflow.kafka.group_id'))
        ->assertJsonPath('data.consumers.0.consumer_name', 'notification-sms')
        ->assertJsonPath('data.consumers.0.processed_total', 2);

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-kafka-replay-1'])
        ->postJson('/api/v1/admin/kafka:replay', [
            'consumer_name' => 'notification-sms',
            'event_ids' => ['event-1'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'kafka_replay_enabled')
        ->assertJsonPath('data.cleared_count', 1);

    expect(DB::table('kafka_consumer_receipts')->count())->toBe(1);

    $this->withToken($token)
        ->withHeaders($headers)
        ->getJson('/api/v1/admin/outbox?status=failed')
        ->assertOk()
        ->assertJsonPath('data.0.id', $failedOutboxId);

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-outbox-retry-1'])
        ->postJson('/api/v1/admin/outbox/'.$failedOutboxId.':retry')
        ->assertOk()
        ->assertJsonPath('status', 'outbox_item_retried')
        ->assertJsonPath('data.id', $failedOutboxId)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.last_error', null);

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-outbox-drain-1'])
        ->postJson('/api/v1/admin/outbox:drain', [
            'limit' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'outbox_drained')
        ->assertJsonPath('data.claimed', 2)
        ->assertJsonPath('data.delivered', 2);

    expect((string) DB::table('outbox_messages')->where('id', $failedOutboxId)->value('status'))->toBe('delivered');
    expect((string) DB::table('outbox_messages')->where('id', $pendingOutboxId)->value('status'))->toBe('delivered');

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-cache-flush-1'])
        ->postJson('/api/v1/admin/cache:flush', [
            'domains' => ['feature-flags', 'rate-limits'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'cache_flushed')
        ->assertJsonPath('data.namespace_invalidations', 2);

    $this->withToken($token)
        ->withHeaders($headers + ['Idempotency-Key' => 'admin-cache-rebuild-1'])
        ->postJson('/api/v1/admin/cache:rebuild', [
            'domains' => ['ops'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'caches_rebuilt')
        ->assertJsonPath('data.warmed.0', 'feature-flags');

    expect(AuditEventRecord::query()->where('action', 'admin.job_retried')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.kafka_replay_enabled')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.outbox_drained')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.cache_flushed')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'admin.caches_rebuilt')->exists())->toBeTrue();
});

it('enforces admin permissions on ops reads and mutations', function (): void {
    $owner = User::factory()->create([
        'email' => 'ops.owner@example.test',
        'password' => 'secret-password',
    ]);

    $ownerToken = observabilityIssueBearerToken($this, 'ops.owner@example.test');
    $tenantId = observabilityCreateTenant($this, $ownerToken, 'Restricted Ops Tenant')->json('data.id');

    $viewer = User::factory()->create([
        'email' => 'ops.viewer@example.test',
        'password' => 'secret-password',
    ]);

    $viewerToken = observabilityIssueBearerToken($this, 'ops.viewer@example.test');
    observabilityEnsureMembership($viewer, $tenantId);
    observabilityGrantPermissions($viewer, $tenantId, ['admin.view']);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/health')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'viewer-cache-flush-1',
        ])
        ->postJson('/api/v1/admin/cache:flush')
        ->assertForbidden();

    $noAdmin = User::factory()->create([
        'email' => 'ops.noadmin@example.test',
        'password' => 'secret-password',
    ]);

    $noAdminToken = observabilityIssueBearerToken($this, 'ops.noadmin@example.test');
    observabilityEnsureMembership($noAdmin, $tenantId);

    $this->withToken($noAdminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/health')
        ->assertForbidden();
});
