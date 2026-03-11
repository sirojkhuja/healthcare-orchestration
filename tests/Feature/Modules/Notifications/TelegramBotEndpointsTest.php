<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Infrastructure\Messaging\NotificationTelegramConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Notifications\FakeTelegramBotGateway;

require_once __DIR__.'/NotificationTestSupport.php';

uses(RefreshDatabase::class);

function telegramNotificationOutboxMessage(string $notificationId): OutboxMessageRecord
{
    return OutboxMessageRecord::query()
        ->where('event_type', 'notification.queued')
        ->where('partition_key', $notificationId)
        ->latest('created_at')
        ->firstOrFail();
}

function telegramNotificationKafkaMessage(OutboxMessageRecord $record): ConsumedKafkaMessage
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

beforeEach(function (): void {
    config()->set('notifications.telegram.webhook_secret', 'telegram-secret');
    config()->set('app.url', 'https://medflow.example');
});

it('manages telegram settings sends diagnostics broadcasts and syncs the bot', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.telegram.manager@openai.com',
        'password' => 'secret-password',
    ]);

    $token = notificationIssueBearerToken($this, 'notifications.telegram.manager@openai.com');
    $tenantId = notificationCreateTenant($this, $token, 'Telegram Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.view',
        'notifications.manage',
        'integrations.manage',
    ]);

    $gateway = new FakeTelegramBotGateway;
    $gateway->queueSuccess('telegram-test-1');
    $gateway->queueSuccess('telegram-broadcast-1');
    $gateway->queueFailure('telegram_blocked', 'The chat blocked the bot.');
    $gateway->queueFailure('telegram_blocked', 'The chat blocked the bot.');
    app()->instance(TelegramBotGateway::class, $gateway);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notification-providers/telegram')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.parse_mode', 'HTML');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-provider-update-1',
        ])
        ->putJson('/api/v1/notification-providers/telegram', [
            'enabled' => true,
            'parse_mode' => 'MarkdownV2',
            'broadcast_chat_ids' => ['1001', '1002'],
            'support_chat_ids' => ['2001'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'telegram_provider_updated')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.parse_mode', 'MarkdownV2')
        ->assertJsonPath('data.broadcast_chat_ids.0', '1001')
        ->assertJsonPath('data.support_chat_ids.0', '2001');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-test-send-1',
        ])
        ->postJson('/api/v1/notifications:test/telegram', [
            'recipient' => [
                'chat_id' => '3001',
            ],
            'message' => 'Test telegram message',
            'metadata' => [
                'parse_mode' => 'HTML',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'notification_test_telegram_sent')
        ->assertJsonPath('data.result.provider.key', 'telegram')
        ->assertJsonPath('data.result.message_id', 'telegram-test-1');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-broadcast-1',
        ])
        ->postJson('/api/v1/telegram/bot:broadcast', [
            'message' => 'Clinic notice',
            'audience' => 'all_configured',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'telegram_broadcast_processed')
        ->assertJsonPath('data.sent_count', 1)
        ->assertJsonPath('data.failed_count', 2)
        ->assertJsonPath('data.results.1.error.code', 'telegram_blocked');

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-sync-1',
        ])
        ->postJson('/api/v1/telegram/bot:sync')
        ->assertOk()
        ->assertJsonPath('status', 'telegram_bot_synced')
        ->assertJsonPath('data.bot.username', 'medflow_bot')
        ->assertJsonPath('data.webhook.url', 'https://medflow.example/api/v1/webhooks/telegram')
        ->assertJsonPath('data.configured_chat_counts.broadcast', 2)
        ->assertJsonPath('data.configured_chat_counts.support', 1);

    expect($gateway->sendRequests())->toHaveCount(4);
    expect($gateway->setWebhookCalls())->toHaveCount(1);
    expect(AuditEventRecord::query()->where('action', 'telegram.broadcast_sent')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'telegram.bot_synced')->exists())->toBeTrue();
});

it('delivers queued telegram notifications once and records sent outcomes', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.telegram.queue@openai.com',
        'password' => 'secret-password',
    ]);

    $token = notificationIssueBearerToken($this, 'notifications.telegram.queue@openai.com');
    $tenantId = notificationCreateTenant($this, $token, 'Telegram Queue Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.view',
        'notifications.manage',
    ]);

    app()->instance(TelegramBotGateway::class, tap(new FakeTelegramBotGateway, function (FakeTelegramBotGateway $gateway): void {
        $gateway->queueSuccess('telegram-queued-1');
    }));

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-provider-update-2',
        ])
        ->putJson('/api/v1/notification-providers/telegram', [
            'enabled' => true,
            'parse_mode' => 'HTML',
            'broadcast_chat_ids' => [],
            'support_chat_ids' => ['9001'],
        ])
        ->assertOk();

    $templateId = notificationCreateTemplate($this, $token, $tenantId, [
        'code' => 'telegram-template',
        'name' => 'Telegram template',
        'channel' => 'telegram',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'telegram-template-create-1')->assertCreated()->json('data.id');

    $notificationId = notificationQueue($this, $token, $tenantId, [
        'template_id' => $templateId,
        'recipient' => [
            'chat_id' => '9001',
        ],
        'variables' => [
            'patient' => [
                'first_name' => 'Malika',
            ],
        ],
        'metadata' => [
            'parse_mode' => 'HTML',
        ],
    ], 'telegram-queue-1')->assertCreated()->json('data.id');

    $message = telegramNotificationKafkaMessage(telegramNotificationOutboxMessage($notificationId));
    $bus = app(IdempotentKafkaConsumerBus::class);
    $handler = app(NotificationTelegramConsumerHandler::class);

    $bus->dispatch($handler, $message);
    $bus->dispatch($handler, $message);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/notifications/'.$notificationId)
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.provider.key', 'telegram')
        ->assertJsonPath('data.provider.message_id', 'telegram-queued-1')
        ->assertJsonPath('data.attempts.used', 1);

    expect(AuditEventRecord::query()->where('action', 'notifications.sent')->where('object_id', $notificationId)->exists())->toBeTrue();
    expect(OutboxMessageRecord::query()->where('event_type', 'notification.sent')->where('partition_key', $notificationId)->exists())->toBeTrue();
});

it('verifies telegram webhooks stores replay-safe deliveries and records support messages', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.telegram.webhook@openai.com',
        'password' => 'secret-password',
    ]);

    $token = notificationIssueBearerToken($this, 'notifications.telegram.webhook@openai.com');
    $tenantId = notificationCreateTenant($this, $token, 'Telegram Webhook Tenant')->json('data.id');
    notificationGrantPermissions($manager, $tenantId, [
        'notifications.manage',
        'integrations.manage',
    ]);

    app()->instance(TelegramBotGateway::class, new FakeTelegramBotGateway('telegram-secret'));

    $this->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'telegram-provider-update-3',
        ])
        ->putJson('/api/v1/notification-providers/telegram', [
            'enabled' => true,
            'parse_mode' => 'HTML',
            'broadcast_chat_ids' => ['7002'],
            'support_chat_ids' => ['7001'],
        ])
        ->assertOk();

    $payload = [
        'update_id' => 5001,
        'message' => [
            'message_id' => 88,
            'text' => 'Need help with scheduling',
            'chat' => [
                'id' => '7001',
                'type' => 'supergroup',
            ],
        ],
    ];

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
        ->postJson('/api/v1/webhooks/telegram', $payload)
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
        ->postJson('/api/v1/webhooks/telegram', $payload)
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
        ->postJson('/api/v1/webhooks/telegram', $payload)
        ->assertStatus(401);

    expect(DB::table('telegram_webhook_deliveries')->count())->toBe(1);
    expect(DB::table('telegram_webhook_deliveries')->value('outcome'))->toBe('processed');
    expect(AuditEventRecord::query()->where('action', 'telegram.support_message_received')->exists())->toBeTrue();
});
