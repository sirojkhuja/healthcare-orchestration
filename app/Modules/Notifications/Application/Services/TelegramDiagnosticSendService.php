<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Data\TelegramSendResultData;
use App\Modules\Notifications\Application\Exceptions\TelegramGatewayException;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final class TelegramDiagnosticSendService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRecipientNormalizer $notificationRecipientNormalizer,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly TelegramParseModeResolver $telegramParseModeResolver,
        private readonly TelegramBotGateway $telegramBotGateway,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function send(array $attributes): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $settings = $this->telegramProviderSettingsRepository->get($tenantId);

        if (! $settings->enabled) {
            throw new ConflictHttpException('The Telegram provider is disabled for the current tenant.');
        }

        $recipient = $this->requiredArray($attributes, 'recipient');
        $metadata = $this->optionalArray($attributes, 'metadata');
        $normalizedRecipient = $this->notificationRecipientNormalizer->normalize(
            NotificationTemplateChannel::TELEGRAM->value,
            $recipient,
        );
        $chatId = $normalizedRecipient['recipient']['chat_id'] ?? null;

        if (! is_string($chatId) || trim($chatId) === '') {
            throw new UnprocessableEntityHttpException('Telegram recipients require recipient.chat_id.');
        }

        $parseMode = $this->telegramParseModeResolver->resolveFromMetadata($settings, $metadata);
        $message = $this->requiredString($attributes, 'message');

        try {
            $result = $this->telegramBotGateway->sendMessage(new TelegramSendRequestData(
                tenantId: $tenantId,
                chatId: $chatId,
                message: $message,
                parseMode: $parseMode,
                metadata: $metadata,
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

        return [
            'channel' => 'telegram',
            'recipient' => $normalizedRecipient['recipient'],
            'message' => $message,
            'parse_mode' => $parseMode,
            'result' => $result->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function optionalArray(array $payload, string $key): array
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return [];
        }

        if (! is_array($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be an object.', $key));
        }

        /** @var array<string, mixed> $value */
        $value = $payload[$key];

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requiredArray(array $payload, string $key): array
    {
        if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be an object.', $key));
        }

        /** @var array<string, mixed> $value */
        $value = $payload[$key];

        return $value;
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
