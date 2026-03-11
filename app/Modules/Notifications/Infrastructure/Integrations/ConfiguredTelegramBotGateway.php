<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Data\TelegramBotProfileData;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Data\TelegramSendResultData;
use App\Modules\Notifications\Application\Data\TelegramWebhookInfoData;
use App\Modules\Notifications\Application\Exceptions\TelegramGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ConfiguredTelegramBotGateway implements TelegramBotGateway
{
    #[\Override]
    public function getMe(): TelegramBotProfileData
    {
        $result = $this->requestResultArray('getMe');

        return new TelegramBotProfileData(
            botId: $this->requiredScalarString($result['id'] ?? null, 'Telegram getMe result must include result.id.'),
            username: $this->requiredScalarString($result['username'] ?? null, 'Telegram getMe result must include result.username.'),
            displayName: isset($result['first_name']) ? $this->requiredScalarString($result['first_name'], 'Telegram getMe result includes an invalid result.first_name value.') : null,
        );
    }

    #[\Override]
    public function getWebhookInfo(): TelegramWebhookInfoData
    {
        $result = $this->requestResultArray('getWebhookInfo');

        return new TelegramWebhookInfoData(
            url: $this->stringValue($result['url'] ?? null),
            hasCustomCertificate: (bool) ($result['has_custom_certificate'] ?? false),
            pendingUpdateCount: is_numeric($result['pending_update_count'] ?? null) ? (int) $result['pending_update_count'] : 0,
            lastErrorDate: is_numeric($result['last_error_date'] ?? null)
                ? CarbonImmutable::createFromTimestampUTC((int) $result['last_error_date'])
                : null,
        );
    }

    #[\Override]
    public function providerKey(): string
    {
        return config()->string('notifications.telegram.provider_key', 'telegram');
    }

    #[\Override]
    public function sendMessage(TelegramSendRequestData $request): TelegramSendResultData
    {
        $result = $this->requestResultArray('sendMessage', [
            'chat_id' => $request->chatId,
            'text' => $request->message,
            'parse_mode' => $request->parseMode,
        ]);

        return new TelegramSendResultData(
            providerKey: $this->providerKey(),
            chatId: $request->chatId,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            messageId: $this->requiredScalarString(
                $result['message_id'] ?? null,
                'Telegram sendMessage result must include result.message_id.',
            ),
        );
    }

    #[\Override]
    public function setWebhook(string $url, string $secretToken): TelegramWebhookInfoData
    {
        $this->requestBooleanResult('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'edited_message'],
        ]);

        return $this->getWebhookInfo();
    }

    #[\Override]
    public function verifyWebhookSecret(string $secretToken): bool
    {
        $configured = trim(config()->string('notifications.telegram.webhook_secret', ''));

        return $configured !== '' && hash_equals($configured, trim($secretToken));
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim(config()->string('notifications.telegram.api_base_url', 'https://api.telegram.org'), '/');
        $token = trim(config()->string('notifications.telegram.bot_token', ''));

        if ($token === '') {
            throw new TelegramGatewayException('telegram_not_configured', 'Telegram bot token is not configured.');
        }

        return $baseUrl.'/bot'.$token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestBooleanResult(string $method, array $payload = []): bool
    {
        $result = $this->request($method, $payload);

        if (! is_bool($result)) {
            throw new TelegramGatewayException('telegram_invalid_response', 'Telegram returned an invalid response payload.');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestResultArray(string $method, array $payload = []): array
    {
        $result = $this->request($method, $payload);

        if (! is_array($result)) {
            throw new TelegramGatewayException('telegram_invalid_response', 'Telegram returned an invalid response payload.');
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($result as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, array $payload = []): mixed
    {
        /** @var Response $response */
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(10)
            ->post($this->baseUrl().'/'.$method, $payload);

        if (! $response->successful()) {
            throw new TelegramGatewayException('telegram_http_error', 'Telegram request failed.');
        }

        /** @var mixed $decoded */
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new TelegramGatewayException('telegram_invalid_response', 'Telegram returned an invalid response payload.');
        }

        if (($decoded['ok'] ?? null) !== true) {
            $description = $this->errorDescription($decoded['description'] ?? null);
            $errorCode = is_numeric($decoded['error_code'] ?? null)
                ? 'telegram_api_'.(int) $decoded['error_code']
                : 'telegram_api_error';

            throw new TelegramGatewayException($errorCode, $description);
        }

        return $decoded['result'] ?? true;
    }

    private function errorDescription(mixed $value): string
    {
        return is_string($value) ? $value : 'Telegram request failed.';
    }

    private function requiredScalarString(mixed $value, string $message): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new TelegramGatewayException('telegram_invalid_response', $message);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new TelegramGatewayException('telegram_invalid_response', $message);
        }

        return $normalized;
    }

    private function stringValue(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
