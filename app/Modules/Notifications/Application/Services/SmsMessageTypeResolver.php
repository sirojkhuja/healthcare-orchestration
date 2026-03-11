<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\SmsMessageType;

final class SmsMessageTypeResolver
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function resolve(array $metadata, ?string $templateCode = null): string
    {
        $explicit = $this->supportedString($metadata['message_type'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $notificationType = $this->normalizedLower($metadata['notification_type'] ?? null);

        if ($notificationType === 'reminder') {
            return SmsMessageType::REMINDER->value;
        }

        if ($notificationType === 'bulk') {
            return SmsMessageType::BULK->value;
        }

        if ($this->booleanValue($metadata['is_bulk'] ?? null)) {
            return SmsMessageType::BULK->value;
        }

        $normalizedTemplateCode = $this->normalizedUpper($templateCode);

        if ($normalizedTemplateCode !== null && str_contains($normalizedTemplateCode, 'OTP')) {
            return SmsMessageType::OTP->value;
        }

        return SmsMessageType::TRANSACTIONAL->value;
    }

    private function booleanValue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function normalizedLower(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizedUpper(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function supportedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, SmsMessageType::all(), true) ? $normalized : null;
    }
}
