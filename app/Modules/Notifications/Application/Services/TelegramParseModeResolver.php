<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TelegramParseModeResolver
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function resolveFromMetadata(TelegramProviderSettingsData $settings, array $metadata): string
    {
        return $this->resolve($settings, $metadata['parse_mode'] ?? null);
    }

    public function resolve(TelegramProviderSettingsData $settings, mixed $candidate): string
    {
        if ($candidate === null) {
            return $settings->parseMode;
        }

        if (! is_string($candidate)) {
            throw new UnprocessableEntityHttpException('The parse_mode field must be a string.');
        }

        $normalized = trim($candidate);

        if (! in_array($normalized, ['HTML', 'MarkdownV2'], true)) {
            throw new UnprocessableEntityHttpException('The parse_mode field must be one of: HTML, MarkdownV2.');
        }

        return $normalized;
    }
}
