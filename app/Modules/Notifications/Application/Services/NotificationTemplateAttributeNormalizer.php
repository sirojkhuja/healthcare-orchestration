<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\NotificationTemplateRenderer;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NotificationTemplateAttributeNormalizer
{
    public function __construct(
        private readonly NotificationTemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: string,
     *     name: string,
     *     channel: string,
     *     description: string|null,
     *     is_active: bool,
     *     subject_template: string|null,
     *     body_template: string,
     *     placeholders: list<string>
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        return $this->normalizeSnapshot([
            'code' => $this->requiredString($attributes['code'] ?? null, 'code'),
            'name' => $this->requiredString($attributes['name'] ?? null, 'name'),
            'channel' => $attributes['channel'] ?? null,
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
            'subject_template' => array_key_exists('subject_template', $attributes)
                ? $this->nullableTemplateString($attributes['subject_template'])
                : null,
            'body_template' => $this->requiredTemplateString($attributes['body_template'] ?? null, 'body_template'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code?: string,
     *     name?: string,
     *     channel?: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     subject_template?: string|null,
     *     body_template?: string,
     *     placeholders?: list<string>
     * }
     */
    public function normalizePatch(NotificationTemplateData $current, array $attributes): array
    {
        $candidate = $this->normalizeSnapshot([
            'code' => array_key_exists('code', $attributes)
                ? $this->requiredString($attributes['code'], 'code')
                : $current->code,
            'name' => array_key_exists('name', $attributes)
                ? $this->requiredString($attributes['name'], 'name')
                : $current->name,
            'channel' => array_key_exists('channel', $attributes) ? $attributes['channel'] : $current->channel,
            'description' => array_key_exists('description', $attributes)
                ? $this->nullableString($attributes['description'])
                : $current->description,
            'is_active' => array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : $current->isActive,
            'subject_template' => array_key_exists('subject_template', $attributes)
                ? $this->nullableTemplateString($attributes['subject_template'])
                : $current->subjectTemplate,
            'body_template' => array_key_exists('body_template', $attributes)
                ? $this->requiredTemplateString($attributes['body_template'], 'body_template')
                : $current->bodyTemplate,
        ]);

        /** @var array{code?: string, name?: string, channel?: string, description?: string|null, is_active?: bool, subject_template?: string|null, body_template?: string, placeholders?: list<string>} $updates */
        $updates = [];

        if ($candidate['code'] !== $current->code) {
            $updates['code'] = $candidate['code'];
        }

        if ($candidate['name'] !== $current->name) {
            $updates['name'] = $candidate['name'];
        }

        if ($candidate['channel'] !== $current->channel) {
            $updates['channel'] = $candidate['channel'];
        }

        if ($candidate['description'] !== $current->description) {
            $updates['description'] = $candidate['description'];
        }

        if ($candidate['is_active'] !== $current->isActive) {
            $updates['is_active'] = $candidate['is_active'];
        }

        if ($candidate['subject_template'] !== $current->subjectTemplate) {
            $updates['subject_template'] = $candidate['subject_template'];
        }

        if ($candidate['body_template'] !== $current->bodyTemplate) {
            $updates['body_template'] = $candidate['body_template'];
        }

        if ($candidate['placeholders'] !== $current->placeholders) {
            $updates['placeholders'] = $candidate['placeholders'];
        }

        return $updates;
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     channel: mixed,
     *     description: string|null,
     *     is_active: bool,
     *     subject_template: string|null,
     *     body_template: string
     * }  $snapshot
     * @return array{
     *     code: string,
     *     name: string,
     *     channel: string,
     *     description: string|null,
     *     is_active: bool,
     *     subject_template: string|null,
     *     body_template: string,
     *     placeholders: list<string>
     * }
     */
    private function normalizeSnapshot(array $snapshot): array
    {
        $channel = $this->normalizeChannel($snapshot['channel']);
        $subjectTemplate = $snapshot['subject_template'];

        if ($channel === NotificationTemplateChannel::EMAIL->value && $subjectTemplate === null) {
            throw new UnprocessableEntityHttpException('Email templates require a subject_template value.');
        }

        if ($channel !== NotificationTemplateChannel::EMAIL->value) {
            $subjectTemplate = null;
        }

        return [
            'code' => mb_strtoupper($snapshot['code']),
            'name' => $snapshot['name'],
            'channel' => $channel,
            'description' => $snapshot['description'],
            'is_active' => $snapshot['is_active'],
            'subject_template' => $subjectTemplate,
            'body_template' => $snapshot['body_template'],
            'placeholders' => $this->renderer->placeholders($subjectTemplate, $snapshot['body_template']),
        ];
    }

    private function normalizeChannel(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The channel field is required.');
        }

        $channel = NotificationTemplateChannel::tryFrom(mb_strtolower(trim($value)));

        if (! $channel instanceof NotificationTemplateChannel) {
            throw new UnprocessableEntityHttpException('The channel field must be one of: email, sms, telegram.');
        }

        return $channel->value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableTemplateString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        return trim($normalized) === '' ? null : $normalized;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $normalized;
    }

    private function requiredTemplateString(mixed $value, string $field): string
    {
        $normalized = $this->nullableTemplateString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $normalized;
    }
}
