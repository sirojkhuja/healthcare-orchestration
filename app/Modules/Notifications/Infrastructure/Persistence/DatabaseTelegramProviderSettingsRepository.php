<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\TelegramChatResolutionData;
use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseTelegramProviderSettingsRepository implements TelegramProviderSettingsRepository
{
    #[\Override]
    public function findChatConflicts(array $chatIds, ?string $excludeTenantId = null): array
    {
        $needle = array_values(array_unique($chatIds));

        if ($needle === []) {
            return [];
        }

        $conflicts = [];

        foreach ($this->rows($excludeTenantId) as $row) {
            $tenantId = $this->stringValue($row->tenant_id ?? null);

            foreach (array_unique([
                ...$this->jsonStringList($row->broadcast_chat_ids ?? null),
                ...$this->jsonStringList($row->support_chat_ids ?? null),
            ]) as $chatId) {
                if (in_array($chatId, $needle, true)) {
                    $conflicts[] = [
                        'tenant_id' => $tenantId,
                        'chat_id' => $chatId,
                    ];
                }
            }
        }

        return $conflicts;
    }

    #[\Override]
    public function get(string $tenantId): TelegramProviderSettingsData
    {
        $row = DB::table('notification_telegram_settings')
            ->where('tenant_id', $tenantId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : $this->defaultData($tenantId);
    }

    #[\Override]
    public function resolveChat(string $chatId): ?TelegramChatResolutionData
    {
        foreach ($this->rows() as $row) {
            $broadcastChatIds = $this->jsonStringList($row->broadcast_chat_ids ?? null);
            $supportChatIds = $this->jsonStringList($row->support_chat_ids ?? null);
            $isBroadcastChat = in_array($chatId, $broadcastChatIds, true);
            $isSupportChat = in_array($chatId, $supportChatIds, true);

            if (! $isBroadcastChat && ! $isSupportChat) {
                continue;
            }

            return new TelegramChatResolutionData(
                tenantId: $this->stringValue($row->tenant_id ?? null),
                chatId: $chatId,
                isSupportChat: $isSupportChat,
                isBroadcastChat: $isBroadcastChat,
            );
        }

        return null;
    }

    #[\Override]
    public function save(string $tenantId, array $attributes): TelegramProviderSettingsData
    {
        $existing = DB::table('notification_telegram_settings')
            ->where('tenant_id', $tenantId)
            ->first(['id', 'created_at']);
        $current = $this->get($tenantId);
        $now = CarbonImmutable::now();

        DB::table('notification_telegram_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) && $existing->id !== ''
                    ? $existing->id
                    : (string) Str::uuid(),
                'enabled' => $attributes['enabled'] ?? $current->enabled,
                'parse_mode' => $attributes['parse_mode'] ?? $current->parseMode,
                'broadcast_chat_ids' => json_encode($attributes['broadcast_chat_ids'] ?? $current->broadcastChatIds, JSON_THROW_ON_ERROR),
                'support_chat_ids' => json_encode($attributes['support_chat_ids'] ?? $current->supportChatIds, JSON_THROW_ON_ERROR),
                'synced_bot_id' => array_key_exists('synced_bot_id', $attributes) ? $attributes['synced_bot_id'] : $current->syncedBotId,
                'synced_bot_username' => array_key_exists('synced_bot_username', $attributes) ? $attributes['synced_bot_username'] : $current->syncedBotUsername,
                'synced_webhook_url' => array_key_exists('synced_webhook_url', $attributes) ? $attributes['synced_webhook_url'] : $current->syncedWebhookUrl,
                'synced_webhook_pending_update_count' => array_key_exists('synced_webhook_pending_update_count', $attributes)
                    ? $attributes['synced_webhook_pending_update_count']
                    : $current->syncedWebhookPendingUpdateCount,
                'synced_webhook_last_error_date' => array_key_exists('synced_webhook_last_error_date', $attributes)
                    ? $attributes['synced_webhook_last_error_date']
                    : $current->syncedWebhookLastErrorDate,
                'last_synced_at' => array_key_exists('last_synced_at', $attributes) ? $attributes['last_synced_at'] : $current->lastSyncedAt,
                'updated_at' => $now,
                'created_at' => is_object($existing) && isset($existing->created_at) ? $existing->created_at : $now,
            ],
        );

        return $this->get($tenantId);
    }

    private function defaultData(string $tenantId): TelegramProviderSettingsData
    {
        $now = CarbonImmutable::now();

        return new TelegramProviderSettingsData(
            tenantId: $tenantId,
            enabled: false,
            parseMode: config()->string('notifications.telegram.default_parse_mode', 'HTML'),
            broadcastChatIds: [],
            supportChatIds: [],
            syncedBotId: null,
            syncedBotUsername: null,
            syncedWebhookUrl: null,
            syncedWebhookPendingUpdateCount: null,
            syncedWebhookLastErrorDate: null,
            lastSyncedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @return list<string>
     */
    private function jsonStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map(
                    static fn (mixed $item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
                    $value,
                ),
                static fn (?string $item): bool => $item !== null,
            ));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->jsonStringList($decoded) : [];
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    /**
     * @return list<stdClass>
     */
    private function rows(?string $excludeTenantId = null): array
    {
        $query = DB::table('notification_telegram_settings');

        if ($excludeTenantId !== null) {
            $query->where('tenant_id', '!=', $excludeTenantId);
        }

        /** @var list<stdClass> $rows */
        $rows = $query->orderBy('tenant_id')->get()->all();

        return $rows;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function toData(stdClass $row): TelegramProviderSettingsData
    {
        return new TelegramProviderSettingsData(
            tenantId: $this->stringValue($row->tenant_id ?? null),
            enabled: (bool) ($row->enabled ?? false),
            parseMode: $this->stringValue($row->parse_mode ?? null),
            broadcastChatIds: $this->jsonStringList($row->broadcast_chat_ids ?? null),
            supportChatIds: $this->jsonStringList($row->support_chat_ids ?? null),
            syncedBotId: $this->nullableString($row->synced_bot_id ?? null),
            syncedBotUsername: $this->nullableString($row->synced_bot_username ?? null),
            syncedWebhookUrl: $this->nullableString($row->synced_webhook_url ?? null),
            syncedWebhookPendingUpdateCount: is_numeric($row->synced_webhook_pending_update_count ?? null)
                ? (int) $row->synced_webhook_pending_update_count
                : null,
            syncedWebhookLastErrorDate: $this->nullableDateTime($row->synced_webhook_last_error_date ?? null),
            lastSyncedAt: $this->nullableDateTime($row->last_synced_at ?? null),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }
}
