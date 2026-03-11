<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\TelegramWebhookDeliveryRepository;
use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TelegramWebhookService
{
    public function __construct(
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly TelegramWebhookDeliveryRepository $telegramWebhookDeliveryRepository,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    public function process(string $secretToken, string $rawPayload, array $payload): array
    {
        if (! $this->telegramBotGateway->verifyWebhookSecret($secretToken)) {
            throw new UnauthorizedHttpException('', 'The Telegram webhook secret token is invalid.');
        }

        $updateId = $this->requiredString($payload['update_id'] ?? null, 'Telegram webhook payload must include update_id.');

        if ($this->telegramWebhookDeliveryRepository->findByUpdateId($this->telegramBotGateway->providerKey(), $updateId) !== null) {
            return ['ok' => true];
        }

        $event = $this->eventPayload($payload);
        $resolution = $event['chat_id'] === null
            ? null
            : $this->telegramProviderSettingsRepository->resolveChat($event['chat_id']);
        $outcome = 'ignored';

        if ($resolution !== null && $resolution->isSupportChat && $event['text'] !== null) {
            $outcome = 'processed';
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'telegram.support_message_received',
                objectType: 'telegram_webhook',
                objectId: $updateId,
                after: [
                    'update_id' => $updateId,
                    'chat_id' => $event['chat_id'],
                    'message_id' => $event['message_id'],
                    'text' => $event['text'],
                    'tenant_id' => $resolution->tenantId,
                ],
            ));
        }

        $this->telegramWebhookDeliveryRepository->create([
            'provider_key' => $this->telegramBotGateway->providerKey(),
            'update_id' => $updateId,
            'event_type' => $event['event_type'],
            'chat_id' => $event['chat_id'],
            'message_id' => $event['message_id'],
            'resolved_tenant_id' => $resolution?->tenantId,
            'payload_hash' => hash('sha256', $rawPayload !== '' ? $rawPayload : json_encode($payload, JSON_THROW_ON_ERROR)),
            'secret_hash' => hash('sha256', trim($secretToken)),
            'outcome' => $outcome,
            'error_code' => null,
            'error_message' => null,
            'processed_at' => CarbonImmutable::now(),
            'payload' => $payload,
            'response' => ['ok' => true],
        ]);

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event_type: string, chat_id: ?string, message_id: ?string, text: ?string}
     */
    private function eventPayload(array $payload): array
    {
        foreach (['message', 'edited_message'] as $eventType) {
            $candidate = $payload[$eventType] ?? null;

            if (! is_array($candidate)) {
                continue;
            }

            $chatId = null;

            if (array_key_exists('chat', $candidate) && is_array($candidate['chat'])) {
                /** @var array<string, mixed> $chat */
                $chat = $candidate['chat'];
                $chatId = $this->nullableString($chat['id'] ?? null);
            }

            return [
                'event_type' => $eventType,
                'chat_id' => $chatId,
                'message_id' => $this->nullableString($candidate['message_id'] ?? null),
                'text' => $this->nullableText($candidate['text'] ?? null),
            ];
        }

        return [
            'event_type' => 'unsupported',
            'chat_id' => null,
            'message_id' => null,
            'text' => null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function requiredString(mixed $value, string $message): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new UnprocessableEntityHttpException($message);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException($message);
        }

        return $normalized;
    }
}
