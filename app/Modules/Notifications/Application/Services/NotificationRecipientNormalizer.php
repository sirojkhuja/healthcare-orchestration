<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NotificationRecipientNormalizer
{
    /**
     * @param  array<string, mixed>  $recipient
     * @return array{recipient: array<string, mixed>, recipient_value: string}
     */
    public function normalize(string $channel, array $recipient): array
    {
        return match ($channel) {
            NotificationTemplateChannel::EMAIL->value => $this->normalizeEmail($recipient),
            NotificationTemplateChannel::SMS->value => $this->normalizeSms($recipient),
            NotificationTemplateChannel::TELEGRAM->value => $this->normalizeTelegram($recipient),
            default => throw new UnprocessableEntityHttpException('Unsupported notification channel.'),
        };
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @return array{recipient: array<string, mixed>, recipient_value: string}
     */
    private function normalizeEmail(array $recipient): array
    {
        $email = mb_strtolower($this->requiredString($recipient, 'email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnprocessableEntityHttpException('Email recipients require a valid recipient.email value.');
        }

        $name = $this->nullableString($recipient, 'name');

        return [
            'recipient' => array_filter([
                'email' => $email,
                'name' => $name,
            ], static fn (mixed $value): bool => $value !== null),
            'recipient_value' => $name === null ? $email : sprintf('%s <%s>', $name, $email),
        ];
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @return array{recipient: array<string, mixed>, recipient_value: string}
     */
    private function normalizeSms(array $recipient): array
    {
        $phone = $this->requiredString($recipient, 'phone_number');

        if (! preg_match('/^\+?[0-9]{7,20}$/', $phone)) {
            throw new UnprocessableEntityHttpException('SMS recipients require a valid recipient.phone_number value.');
        }

        return [
            'recipient' => [
                'phone_number' => $phone,
            ],
            'recipient_value' => $phone,
        ];
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @return array{recipient: array<string, mixed>, recipient_value: string}
     */
    private function normalizeTelegram(array $recipient): array
    {
        $chatId = $recipient['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            throw new UnprocessableEntityHttpException('Telegram recipients require recipient.chat_id.');
        }

        $normalized = trim((string) $chatId);

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('Telegram recipients require recipient.chat_id.');
        }

        return [
            'recipient' => [
                'chat_id' => $normalized,
            ],
            'recipient_value' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (! is_string($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The recipient.%s field must be a string.', $key));
        }

        $value = trim($payload[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The recipient.%s field is required.', $key));
        }

        $value = trim($payload[$key]);

        if ($value === '') {
            throw new UnprocessableEntityHttpException(sprintf('The recipient.%s field is required.', $key));
        }

        return $value;
    }
}
