<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class TelegramSyncResultData
{
    public function __construct(
        public TelegramProviderSettingsData $settings,
        public TelegramBotProfileData $bot,
        public TelegramWebhookInfoData $webhook,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => 'telegram',
            'bot' => $this->bot->toArray(),
            'webhook' => $this->webhook->toArray(),
            'settings' => $this->settings->toArray(),
            'configured_chat_counts' => [
                'broadcast' => count($this->settings->broadcastChatIds),
                'support' => count($this->settings->supportChatIds),
            ],
        ];
    }
}
