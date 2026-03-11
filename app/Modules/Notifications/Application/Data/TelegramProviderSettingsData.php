<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TelegramProviderSettingsData
{
    /**
     * @param  list<string>  $broadcastChatIds
     * @param  list<string>  $supportChatIds
     */
    public function __construct(
        public string $tenantId,
        public bool $enabled,
        public string $parseMode,
        public array $broadcastChatIds,
        public array $supportChatIds,
        public ?string $syncedBotId,
        public ?string $syncedBotUsername,
        public ?string $syncedWebhookUrl,
        public ?int $syncedWebhookPendingUpdateCount,
        public ?CarbonImmutable $syncedWebhookLastErrorDate,
        public ?CarbonImmutable $lastSyncedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => 'telegram',
            'enabled' => $this->enabled,
            'parse_mode' => $this->parseMode,
            'broadcast_chat_ids' => $this->broadcastChatIds,
            'support_chat_ids' => $this->supportChatIds,
            'sync' => [
                'bot_id' => $this->syncedBotId,
                'bot_username' => $this->syncedBotUsername,
                'webhook_url' => $this->syncedWebhookUrl,
                'webhook_pending_update_count' => $this->syncedWebhookPendingUpdateCount,
                'webhook_last_error_date' => $this->syncedWebhookLastErrorDate?->toIso8601String(),
                'last_synced_at' => $this->lastSyncedAt?->toIso8601String(),
            ],
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
