<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\TelegramChatResolutionData;
use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;

interface TelegramProviderSettingsRepository
{
    public function get(string $tenantId): TelegramProviderSettingsData;

    /**
     * @param  array{
     *     enabled?: bool,
     *     parse_mode?: string,
     *     broadcast_chat_ids?: list<string>,
     *     support_chat_ids?: list<string>,
     *     synced_bot_id?: ?string,
     *     synced_bot_username?: ?string,
     *     synced_webhook_url?: ?string,
     *     synced_webhook_pending_update_count?: ?int,
     *     synced_webhook_last_error_date?: ?\Carbon\CarbonImmutable,
     *     last_synced_at?: ?\Carbon\CarbonImmutable
     * }  $attributes
     */
    public function save(string $tenantId, array $attributes): TelegramProviderSettingsData;

    public function resolveChat(string $chatId): ?TelegramChatResolutionData;

    /**
     * @param  list<string>  $chatIds
     * @return list<array{tenant_id: string, chat_id: string}>
     */
    public function findChatConflicts(array $chatIds, ?string $excludeTenantId = null): array;
}
