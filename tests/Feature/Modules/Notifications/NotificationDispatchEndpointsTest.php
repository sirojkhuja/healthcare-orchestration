<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/NotificationTestSupport.php';

uses(RefreshDatabase::class);

it('queues lists retries and cancels notifications with audit and outbox coverage', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.dispatch.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.dispatch.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.dispatch.manager@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.dispatch.viewer@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'Notification Dispatch Tenant')->json('data.id');

    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);

    $templateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'appointment-reminder-dispatch',
        'name' => 'Dispatch reminder',
        'channel' => 'email',
        'subject_template' => 'Reminder for {{patient.first_name}}',
        'body_template' => 'Hello {{patient.first_name}}, your visit is at {{appointment.start_at}}.',
    ], 'notifications-dispatch-template-1')->assertCreated()->json('data.id');

    $queueResponse = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'email' => 'amina@example.com',
            'name' => 'Amina Karimova',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Amina',
            ],
            'appointment' => [
                'start_at' => '2026-03-12 09:00',
            ],
        ],
        'metadata' => [
            'object_type' => 'appointment',
            'object_id' => 'APT-123',
        ],
    ], 'notifications-queue-1')
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'notifications-queue-1')
        ->assertJsonPath('status', 'notification_queued')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.template.id', $templateId)
        ->assertJsonPath('data.template.channel', 'email')
        ->assertJsonPath('data.recipient.email', 'amina@example.com')
        ->assertJsonPath('data.rendered_subject', 'Reminder for Amina')
        ->assertJsonPath('data.rendered_body', 'Hello Amina, your visit is at 2026-03-12 09:00.')
        ->assertJsonPath('data.attempts.used', 0)
        ->assertJsonPath('data.attempts.max', 3);

    $notificationId = $queueResponse->json('data.id');

    notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'email' => 'amina@example.com',
            'name' => 'Amina Karimova',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Amina',
            ],
            'appointment' => [
                'start_at' => '2026-03-12 09:00',
            ],
        ],
        'metadata' => [
            'object_type' => 'appointment',
            'object_id' => 'APT-123',
        ],
    ], 'notifications-queue-1')
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('data.id', $notificationId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications?status=queued&channel=email&q=amina')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.status', 'queued')
        ->assertJsonPath('meta.filters.channel', 'email')
        ->assertJsonPath('data.0.id', $notificationId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.id', $notificationId)
        ->assertJsonPath('data.status', 'queued');

    $cancelNotificationId = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'email' => 'cancel@example.com',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Cancel',
            ],
            'appointment' => [
                'start_at' => '2026-03-12 11:00',
            ],
        ],
    ], 'notifications-queue-2')->assertCreated()->json('data.id');

    notificationCancel($this, $managerToken, $tenantId, $cancelNotificationId, [
        'reason' => 'Reminder no longer needed.',
    ], 'notifications-cancel-1')
        ->assertOk()
        ->assertJsonPath('status', 'notification_canceled')
        ->assertJsonPath('data.status', 'canceled')
        ->assertJsonPath('data.canceled_reason', 'Reminder no longer needed.');

    DB::table('notifications')
        ->where('id', $notificationId)
        ->update([
            'status' => 'failed',
            'attempts' => 1,
            'last_error_code' => 'temporary_failure',
            'last_error_message' => 'Provider timeout.',
            'failed_at' => CarbonImmutable::now(),
            'last_attempt_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

    notificationRetry($this, $managerToken, $tenantId, $notificationId, 'notifications-retry-1')
        ->assertOk()
        ->assertJsonPath('status', 'notification_retried')
        ->assertJsonPath('data.id', $notificationId)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.failure', null);

    expect(AuditEventRecord::query()->where('action', 'notifications.queued')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'notifications.canceled')->where('object_id', $cancelNotificationId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'notifications.retried')->where('object_id', $notificationId)->exists())->toBeTrue();

    expect(OutboxMessageRecord::query()->where('event_type', 'notification.queued')->count())->toBe(2);
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.canceled')->where('partition_key', $cancelNotificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.retried')->where('partition_key', $notificationId)->exists())->toBeTrue();
});

it('enforces notification permissions validation and lifecycle guards', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.guard.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.guard.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $manageOnly = User::factory()->create([
        'email' => 'notifications.guard.manage@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.guard.manager@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.guard.viewer@openai.com');
    $manageOnlyToken = notificationIssueBearerToken($this, 'notifications.guard.manage@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'Notification Guard Tenant')->json('data.id');

    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);
    notificationGrantPermissions($manageOnly, $tenantId, ['notifications.manage']);

    $inactiveTemplateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'inactive-reminder',
        'name' => 'Inactive reminder',
        'channel' => 'email',
        'subject_template' => 'Hello {{patient.first_name}}',
        'body_template' => 'Body {{patient.first_name}}',
        'is_active' => false,
    ], 'notifications-guard-template-1')->assertCreated()->json('data.id');

    notificationQueue($this, $viewerToken, $tenantId, [
        'template_id' => $inactiveTemplateId,
        'recipient' => [
            'email' => 'viewer@example.com',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Viewer',
            ],
        ],
    ], 'notifications-guard-viewer')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($manageOnlyToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $inactiveTemplateId,
        'recipient' => [
            'email' => 'inactive@example.com',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Inactive',
            ],
        ],
    ], 'notifications-guard-inactive')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $smsTemplateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'sms-reminder',
        'name' => 'SMS reminder',
        'channel' => 'sms',
        'body_template' => 'Reminder {{patient.first_name}}',
    ], 'notifications-guard-template-2')->assertCreated()->json('data.id');

    notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $smsTemplateId,
        'recipient' => [
            'phone_number' => 'not-a-phone',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Bad',
            ],
        ],
    ], 'notifications-guard-invalid-phone')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $notificationId = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $smsTemplateId,
        'recipient' => [
            'phone_number' => '+998901234567',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Guard',
            ],
        ],
    ], 'notifications-guard-queue-1')
        ->assertCreated()
        ->json('data.id');

    DB::table('notifications')
        ->where('id', $notificationId)
        ->update([
            'status' => 'sent',
            'sent_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

    notificationCancel($this, $managerToken, $tenantId, $notificationId, [], 'notifications-guard-cancel-sent')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $failedNotificationId = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $smsTemplateId,
        'recipient' => [
            'phone_number' => '+998909999999',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Retry',
            ],
        ],
    ], 'notifications-guard-queue-2')
        ->assertCreated()
        ->json('data.id');

    DB::table('notifications')
        ->where('id', $failedNotificationId)
        ->update([
            'status' => 'failed',
            'attempts' => 3,
            'last_error_code' => 'delivery_failed',
            'last_error_message' => 'Budget exhausted.',
            'failed_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

    notificationRetry($this, $managerToken, $tenantId, $failedNotificationId, 'notifications-guard-retry-exhausted')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');
});
