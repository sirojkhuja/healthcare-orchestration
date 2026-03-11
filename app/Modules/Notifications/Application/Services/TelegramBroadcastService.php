<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\TelegramBroadcastResultData;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Data\TelegramSendResultData;
use App\Modules\Notifications\Application\Exceptions\TelegramGatewayException;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final class TelegramBroadcastService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly TelegramParseModeResolver $telegramParseModeResolver,
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function broadcast(array $attributes): TelegramBroadcastResultData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $settings = $this->telegramProviderSettingsRepository->get($tenantId);

        if (! $settings->enabled) {
            throw new ConflictHttpException('The Telegram provider is disabled for the current tenant.');
        }

        $message = $this->requiredString($attributes, 'message');
        $audience = $this->audience($attributes['audience'] ?? null);
        $chatIds = $this->chatIds($attributes['chat_ids'] ?? null, $settings, $audience);
        $parseMode = $this->telegramParseModeResolver->resolve($settings, $attributes['parse_mode'] ?? null);
        $results = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($chatIds as $chatId) {
            try {
                $result = $this->telegramBotGateway->sendMessage(new TelegramSendRequestData(
                    tenantId: $tenantId,
                    chatId: $chatId,
                    message: $message,
                    parseMode: $parseMode,
                ));
            } catch (Throwable $exception) {
                $result = new TelegramSendResultData(
                    providerKey: $this->telegramBotGateway->providerKey(),
                    chatId: $chatId,
                    status: 'failed',
                    occurredAt: CarbonImmutable::now(),
                    errorCode: $exception instanceof TelegramGatewayException ? $exception->errorCode() : 'telegram_provider_error',
                    errorMessage: $exception->getMessage(),
                );
            }

            $results[] = $result->toArray();

            if ($result->status === 'sent') {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $summary = new TelegramBroadcastResultData(
            audience: $audience,
            chatIds: $chatIds,
            results: $results,
            sentCount: $sentCount,
            failedCount: $failedCount,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'telegram.broadcast_sent',
            objectType: 'telegram_broadcast',
            objectId: (string) Str::uuid(),
            after: $summary->toArray(),
            metadata: [
                'message' => $message,
                'parse_mode' => $parseMode,
            ],
        ));

        return $summary;
    }

    private function audience(mixed $value): string
    {
        if ($value === null) {
            return 'configured_broadcast';
        }

        if (! is_string($value) || ! in_array($value, ['configured_broadcast', 'configured_support', 'all_configured'], true)) {
            throw new UnprocessableEntityHttpException('The audience field must be one of: configured_broadcast, configured_support, all_configured.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function chatIds(mixed $value, \App\Modules\Notifications\Application\Data\TelegramProviderSettingsData $settings, string $audience): array
    {
        if (is_array($value) && $value !== []) {
            $normalized = [];

            foreach ($value as $item) {
                if (! is_string($item) && ! is_int($item)) {
                    throw new UnprocessableEntityHttpException('The chat_ids field must contain chat ids.');
                }

                $chatId = trim((string) $item);

                if ($chatId === '') {
                    throw new UnprocessableEntityHttpException('The chat_ids field must contain chat ids.');
                }

                if (! in_array($chatId, $normalized, true)) {
                    $normalized[] = $chatId;
                }
            }

            return $normalized;
        }

        $resolved = match ($audience) {
            'configured_support' => $settings->supportChatIds,
            'all_configured' => array_values(array_unique([...$settings->broadcastChatIds, ...$settings->supportChatIds])),
            default => $settings->broadcastChatIds,
        };

        if ($resolved === []) {
            throw new UnprocessableEntityHttpException('The broadcast recipient set is empty.');
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $key));
        }

        return trim($payload[$key]);
    }
}
