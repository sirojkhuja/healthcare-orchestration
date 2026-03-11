<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TelegramProviderSettingsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly TelegramParseModeResolver $telegramParseModeResolver,
    ) {}

    public function get(): TelegramProviderSettingsData
    {
        return $this->telegramProviderSettingsRepository->get($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): TelegramProviderSettingsData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $current = $this->telegramProviderSettingsRepository->get($tenantId);
        $broadcastChatIds = $this->chatIds($attributes['broadcast_chat_ids'] ?? null, 'broadcast_chat_ids');
        $supportChatIds = $this->chatIds($attributes['support_chat_ids'] ?? null, 'support_chat_ids');
        $allChatIds = array_values(array_unique([...$broadcastChatIds, ...$supportChatIds]));

        foreach ($this->telegramProviderSettingsRepository->findChatConflicts($allChatIds, $tenantId) as $conflict) {
            throw new UnprocessableEntityHttpException(sprintf(
                'The chat_id %s is already assigned to tenant %s.',
                $conflict['chat_id'],
                $conflict['tenant_id'],
            ));
        }

        return $this->telegramProviderSettingsRepository->save($tenantId, [
            'enabled' => $this->requiredBool($attributes['enabled'] ?? null),
            'parse_mode' => $this->telegramParseModeResolver->resolve($current, $attributes['parse_mode'] ?? null),
            'broadcast_chat_ids' => $broadcastChatIds,
            'support_chat_ids' => $supportChatIds,
        ]);
    }

    /**
     * @return list<string>
     */
    private function chatIds(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be an array.', $field));
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_string($item) && ! is_int($item)) {
                throw new UnprocessableEntityHttpException(sprintf('The %s field must contain chat ids.', $field));
            }

            $chatId = trim((string) $item);

            if ($chatId === '') {
                throw new UnprocessableEntityHttpException(sprintf('The %s field must contain chat ids.', $field));
            }

            if (! in_array($chatId, $normalized, true)) {
                $normalized[] = $chatId;
            }
        }

        return $normalized;
    }

    private function requiredBool(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new UnprocessableEntityHttpException('The enabled field is required.');
        }

        return $value;
    }
}
