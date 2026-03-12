<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Notifications\Application\Contracts\EmailGateway;
use App\Modules\Notifications\Infrastructure\Messaging\NotificationEmailConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Notifications\FakeEmailGateway;

require_once __DIR__.'/NotificationTestSupport.php';

uses(RefreshDatabase::class);

function emailNotificationOutboxMessage(string $notificationId): OutboxMessageRecord
{
    return OutboxMessageRecord::query()
        ->where('event_type', 'notification.queued')
        ->where('partition_key', $notificationId)
        ->latest('created_at')
        ->firstOrFail();
}

function emailNotificationKafkaMessage(OutboxMessageRecord $record): ConsumedKafkaMessage
{
    /** @var array<string, string> $headers */
    $headers = is_array($record->headers) ? $record->headers : [];

    return new ConsumedKafkaMessage(
        messageId: $record->event_id,
        topic: $record->topic,
        partition: 0,
        key: $record->partition_key,
        headers: [
            ...$headers,
            'event_id' => $record->event_id,
            'event_type' => $record->event_type,
            'tenant_id' => $record->tenant_id ?? '',
        ],
        payload: $record->payload,
    );
}

it('manages email settings sends diagnostics sends direct emails and lists email events', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.email.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.email.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.email.manager@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.email.viewer@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'Email Channel Tenant')->json('data.id');

    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);

    $gateway = new FakeEmailGateway;
    $gateway->queueSuccess('email-test-1');
    $gateway->queueSuccess('email-direct-1');
    app()->instance(EmailGateway::class, $gateway);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notification-providers/email')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'email');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-provider-update-1',
        ])
        ->putJson('/api/v1/notification-providers/email', [
            'enabled' => true,
            'from_address' => 'noreply@openai.com',
            'from_name' => 'MedFlow Notifications',
            'reply_to_address' => 'support@openai.com',
            'reply_to_name' => 'MedFlow Support',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'email_provider_updated')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.from_address', 'noreply@openai.com')
        ->assertJsonPath('data.reply_to_address', 'support@openai.com');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-test-send-1',
        ])
        ->postJson('/api/v1/notifications:test/email', [
            'recipient' => [
                'email' => 'patient.email+test@openai.com',
                'name' => 'Amina Karimova',
            ],
            'subject' => 'Diagnostic subject',
            'body' => 'Diagnostic body',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'notification_test_email_sent')
        ->assertJsonPath('data.result.provider.key', 'email')
        ->assertJsonPath('data.result.provider.message_id', 'email-test-1');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-direct-send-1',
        ])
        ->postJson('/api/v1/email:send', [
            'recipient' => [
                'email' => 'patient.direct@openai.com',
                'name' => 'Malika Rustamova',
            ],
            'subject' => 'Invoice available',
            'body' => 'Your invoice is ready.',
            'metadata' => [
                'object_type' => 'invoice',
                'object_id' => 'INV-1001',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'email_sent')
        ->assertJsonPath('data.result.provider.key', 'email')
        ->assertJsonPath('data.result.provider.message_id', 'email-direct-1')
        ->assertJsonPath('data.event.source', 'direct')
        ->assertJsonPath('data.event.event_type', 'sent');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/email/events?source=direct&event_type=sent&q=invoice')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.source', 'direct')
        ->assertJsonPath('meta.filters.event_type', 'sent')
        ->assertJsonPath('data.0.provider.message_id', 'email-direct-1')
        ->assertJsonPath('data.0.recipient.email', 'patient.direct@openai.com');

    expect(DB::table('notification_email_events')->count())->toBe(1);
    expect(DB::table('notifications')->count())->toBe(0);
    expect($gateway->requests())->toHaveCount(2);
    expect(AuditEventRecord::query()->where('action', 'notifications.email_sent')->exists())->toBeTrue();
});

it('enforces email notification permissions and returns failed direct-send outcomes', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.email.guard.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.email.guard.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $manageOnly = User::factory()->create([
        'email' => 'notifications.email.guard.manage@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.email.guard.manager@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.email.guard.viewer@openai.com');
    $manageOnlyToken = notificationIssueBearerToken($this, 'notifications.email.guard.manage@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'Email Guard Tenant')->json('data.id');

    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);
    notificationGrantPermissions($manageOnly, $tenantId, ['notifications.manage']);

    $gateway = new FakeEmailGateway;
    $gateway->queueFailure('smtp_timeout', 'SMTP timed out.');
    app()->instance(EmailGateway::class, $gateway);

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-guard-viewer-test',
        ])
        ->postJson('/api/v1/notifications:test/email', [
            'recipient' => ['email' => 'viewer@openai.com'],
            'subject' => 'Blocked',
            'body' => 'Blocked',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($manageOnlyToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/email/events')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-provider-update-guard',
        ])
        ->putJson('/api/v1/notification-providers/email', [
            'enabled' => true,
            'from_address' => 'noreply@openai.com',
            'from_name' => 'MedFlow Notifications',
        ])
        ->assertOk();

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-direct-send-guard',
        ])
        ->postJson('/api/v1/email:send', [
            'recipient' => [
                'email' => 'failed@openai.com',
            ],
            'subject' => 'Will fail',
            'body' => 'Failure body',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'email_failed')
        ->assertJsonPath('data.result.error.code', 'smtp_timeout')
        ->assertJsonPath('data.event.event_type', 'failed');

    expect(DB::table('notification_email_events')->count())->toBe(1);
    expect(AuditEventRecord::query()->where('action', 'notifications.email_failed')->exists())->toBeTrue();
});

it('delivers queued email notifications once and records email events', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.email.queue@openai.com',
        'password' => 'secret-password',
    ]);

    $token = notificationIssueBearerToken($this, 'notifications.email.queue@openai.com');
    $tenantId = notificationCreateTenant($this, $token, 'Email Queue Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);

    $gateway = new FakeEmailGateway;
    $gateway->queueSuccess('email-queued-1');
    app()->instance(EmailGateway::class, $gateway);

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-provider-update-queue',
        ])
        ->putJson('/api/v1/notification-providers/email', [
            'enabled' => true,
            'from_address' => 'noreply@openai.com',
            'from_name' => 'MedFlow Notifications',
        ])
        ->assertOk();

    $templateId = notificationCreateTemplate($this, $token, $tenantId, [
        'code' => 'email-queue-template',
        'name' => 'Email queue template',
        'channel' => 'email',
        'subject_template' => 'Reminder for {{patient.first_name}}',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'email-template-create-1')->assertCreated()->json('data.id');

    $notificationId = notificationQueue($this, $token, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'email' => 'queued.patient@openai.com',
            'name' => 'Queued Patient',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Queued',
            ],
        ],
    ], 'email-queue-1')->assertCreated()->json('data.id');

    $message = emailNotificationKafkaMessage(emailNotificationOutboxMessage($notificationId));
    $bus = app(IdempotentKafkaConsumerBus::class);
    $handler = app(NotificationEmailConsumerHandler::class);

    $bus->dispatch($handler, $message);
    $bus->dispatch($handler, $message);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.provider.key', 'email')
        ->assertJsonPath('data.provider.message_id', 'email-queued-1')
        ->assertJsonPath('data.attempts.used', 1);

    expect(DB::table('notification_email_events')->count())->toBe(1);
    expect(DB::table('notification_email_events')->value('source'))->toBe('notification');
    expect(AuditEventRecord::query()->where('action', 'notifications.sent')->where('object_id', $notificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.sent')->where('partition_key', $notificationId)->exists())->toBeTrue();
});

it('marks queued email notifications failed when the provider rejects delivery', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.email.failed@openai.com',
        'password' => 'secret-password',
    ]);

    $token = notificationIssueBearerToken($this, 'notifications.email.failed@openai.com');
    $tenantId = notificationCreateTenant($this, $token, 'Email Failure Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);

    $gateway = new FakeEmailGateway;
    $gateway->queueFailure('smtp_rejected', 'The upstream SMTP relay rejected the recipient.');
    app()->instance(EmailGateway::class, $gateway);

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'email-provider-update-failure',
        ])
        ->putJson('/api/v1/notification-providers/email', [
            'enabled' => true,
            'from_address' => 'noreply@openai.com',
            'from_name' => 'MedFlow Notifications',
        ])
        ->assertOk();

    $templateId = notificationCreateTemplate($this, $token, $tenantId, [
        'code' => 'email-failure-template',
        'name' => 'Email failure template',
        'channel' => 'email',
        'subject_template' => 'Failed delivery',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'email-template-create-2')->assertCreated()->json('data.id');

    $notificationId = notificationQueue($this, $token, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'email' => 'failed.patient@openai.com',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Failed',
            ],
        ],
    ], 'email-queue-2')->assertCreated()->json('data.id');

    app(NotificationEmailConsumerHandler::class)->handle(
        emailNotificationKafkaMessage(emailNotificationOutboxMessage($notificationId)),
    );

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure.code', 'smtp_rejected')
        ->assertJsonPath('data.attempts.used', 1);

    expect(DB::table('notification_email_events')->value('event_type'))->toBe('failed');
    expect(DB::table('notification_email_events')->value('error_code'))->toBe('smtp_rejected');
    expect(AuditEventRecord::query()->where('action', 'notifications.failed')->where('object_id', $notificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.failed')->where('partition_key', $notificationId)->exists())->toBeTrue();
});
