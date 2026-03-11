<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Notifications\Application\Contracts\SmsProviderRegistry;
use App\Modules\Notifications\Infrastructure\Messaging\NotificationSmsConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Notifications\FakeSmsProvider;
use Tests\Fixtures\Notifications\FakeSmsProviderRegistry;

require_once __DIR__.'/NotificationTestSupport.php';

uses(RefreshDatabase::class);

function notificationOutboxMessage(string $notificationId): OutboxMessageRecord
{
    return OutboxMessageRecord::query()
        ->where('event_type', 'notification.queued')
        ->where('partition_key', $notificationId)
        ->latest('created_at')
        ->firstOrFail();
}

function notificationKafkaMessage(OutboxMessageRecord $record): ConsumedKafkaMessage
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

it('lists updates and uses sms routing for test and direct provider sends', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.sms-routing.manager@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.sms-routing.manager@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'SMS Routing Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.view',
        'notifications.manage',
        'integrations.manage',
    ]);

    $eskiz = new FakeSmsProvider('eskiz', 'Eskiz');
    $eskiz->queueSuccess('eskiz-direct-1');
    $playMobile = new FakeSmsProvider('playmobile', 'Play Mobile');
    $playMobile->queueSuccess('playmobile-test-1');
    $textUp = new FakeSmsProvider('textup', 'TextUp');
    app()->instance(SmsProviderRegistry::class, new FakeSmsProviderRegistry([$eskiz, $playMobile, $textUp]));

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notification-providers/sms')
        ->assertOk()
        ->assertJsonPath('data.providers.0.key', 'eskiz')
        ->assertJsonPath('data.routes.1.message_type', 'reminder')
        ->assertJsonPath('data.routes.1.source', 'default');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'sms-provider-routes-1',
        ])
        ->putJson('/api/v1/notification-providers/sms', [
            'routes' => [
                [
                    'message_type' => 'reminder',
                    'providers' => ['playmobile', 'eskiz'],
                ],
                [
                    'message_type' => 'transactional',
                    'providers' => ['eskiz'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'sms_providers_updated')
        ->assertJsonPath('data.routes.1.message_type', 'reminder')
        ->assertJsonPath('data.routes.1.providers.0', 'playmobile')
        ->assertJsonPath('data.routes.1.source', 'custom');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'notifications-test-sms-1',
        ])
        ->postJson('/api/v1/notifications:test/sms', [
            'recipient' => [
                'phone_number' => '+998901234567',
            ],
            'message' => 'Reminder for your visit.',
            'message_type' => 'reminder',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'notification_test_sms_sent')
        ->assertJsonPath('data.result.provider.key', 'playmobile')
        ->assertJsonPath('data.result.attempted_count', 1);

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'integrations-eskiz-send-1',
        ])
        ->postJson('/api/v1/integrations/eskiz:send', [
            'recipient' => [
                'phone_number' => '+998909876543',
            ],
            'message' => 'Manual provider check.',
            'message_type' => 'transactional',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'sms_sent')
        ->assertJsonPath('data.result.provider.key', 'eskiz')
        ->assertJsonPath('data.result.attempted_count', 1);

    expect($playMobile->requests())->toHaveCount(1);
    expect($eskiz->requests())->toHaveCount(1);
});

it('processes queued sms notifications with provider failover and suppresses replayed consumer messages', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.sms-consumer.manager@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.sms-consumer.manager@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'SMS Consumer Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.view',
        'notifications.manage',
    ]);

    $eskiz = new FakeSmsProvider('eskiz', 'Eskiz');
    $eskiz->queueSuccess('eskiz-failover-1');
    $playMobile = new FakeSmsProvider('playmobile', 'Play Mobile');
    $playMobile->queueFailure('carrier_timeout', 'Play Mobile timed out.');
    $textUp = new FakeSmsProvider('textup', 'TextUp');
    app()->instance(SmsProviderRegistry::class, new FakeSmsProviderRegistry([$eskiz, $playMobile, $textUp]));

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'sms-provider-routes-2',
        ])
        ->putJson('/api/v1/notification-providers/sms', [
            'routes' => [
                [
                    'message_type' => 'transactional',
                    'providers' => ['playmobile', 'eskiz'],
                ],
            ],
        ])
        ->assertOk();

    $templateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'sms-routing-template',
        'name' => 'SMS routing template',
        'channel' => 'sms',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'notifications-sms-template-1')->assertCreated()->json('data.id');

    $notificationId = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'phone_number' => '+998901111111',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Amina',
            ],
        ],
        'metadata' => [
            'message_type' => 'transactional',
        ],
    ], 'notifications-sms-queue-1')->assertCreated()->json('data.id');

    $message = notificationKafkaMessage(notificationOutboxMessage($notificationId));
    $bus = app(IdempotentKafkaConsumerBus::class);
    $handler = app(NotificationSmsConsumerHandler::class);

    $bus->dispatch($handler, $message);
    $bus->dispatch($handler, $message);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.provider.key', 'eskiz')
        ->assertJsonPath('data.provider.message_id', 'eskiz-failover-1')
        ->assertJsonPath('data.attempts.used', 2)
        ->assertJsonPath('data.failure', null);

    expect($playMobile->requests())->toHaveCount(1);
    expect($eskiz->requests())->toHaveCount(1);
    expect(AuditEventRecord::query()->where('action', 'notifications.sent')->where('object_id', $notificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.sent')->where('partition_key', $notificationId)->exists())->toBeTrue();
});

it('marks sms notifications failed when all configured providers reject the delivery', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.sms-failed.manager@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.sms-failed.manager@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'SMS Failure Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.view',
        'notifications.manage',
    ]);

    $eskiz = new FakeSmsProvider('eskiz', 'Eskiz');
    $eskiz->queueFailure('carrier_timeout', 'Eskiz timed out.');
    $playMobile = new FakeSmsProvider('playmobile', 'Play Mobile');
    $playMobile->queueFailure('delivery_rejected', 'Play Mobile rejected the recipient.');
    $textUp = new FakeSmsProvider('textup', 'TextUp');
    app()->instance(SmsProviderRegistry::class, new FakeSmsProviderRegistry([$eskiz, $playMobile, $textUp]));

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'sms-provider-routes-3',
        ])
        ->putJson('/api/v1/notification-providers/sms', [
            'routes' => [
                [
                    'message_type' => 'transactional',
                    'providers' => ['eskiz', 'playmobile'],
                ],
            ],
        ])
        ->assertOk();

    $templateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'sms-failure-template',
        'name' => 'SMS failure template',
        'channel' => 'sms',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'notifications-sms-template-2')->assertCreated()->json('data.id');

    $notificationId = notificationQueue($this, $managerToken, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'phone_number' => '+998902222222',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Zarina',
            ],
        ],
        'metadata' => [
            'message_type' => 'transactional',
        ],
    ], 'notifications-sms-queue-2')->assertCreated()->json('data.id');

    app(NotificationSmsConsumerHandler::class)->handle(
        notificationKafkaMessage(notificationOutboxMessage($notificationId)),
    );

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.provider.key', 'playmobile')
        ->assertJsonPath('data.provider.message_id', null)
        ->assertJsonPath('data.attempts.used', 2)
        ->assertJsonPath('data.failure.code', 'delivery_rejected')
        ->assertJsonPath('data.failure.message', 'Play Mobile rejected the recipient.');

    expect($eskiz->requests())->toHaveCount(1);
    expect($playMobile->requests())->toHaveCount(1);
    expect(AuditEventRecord::query()->where('action', 'notifications.failed')->where('object_id', $notificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.failed')->where('partition_key', $notificationId)->exists())->toBeTrue();
});
