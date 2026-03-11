<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Data\NotificationData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use stdClass;

final class NotificationRecordMapper
{
    public function toData(stdClass $row): NotificationData
    {
        return new NotificationData(
            notificationId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            templateId: $this->stringValue($row->template_id ?? null),
            templateCode: $this->stringValue($row->template_code ?? null),
            templateVersion: $this->intValue($row->template_version ?? null),
            channel: $this->stringValue($row->channel ?? null),
            recipient: $this->jsonObject($row->recipient ?? null),
            recipientValue: $this->stringValue($row->recipient_value ?? null),
            renderedSubject: $this->nullableString($row->rendered_subject ?? null),
            renderedBody: $this->stringValue($row->rendered_body ?? null),
            variables: $this->jsonObject($row->variables ?? null),
            metadata: $this->jsonObject($row->metadata ?? null),
            status: $this->stringValue($row->status ?? null),
            attempts: $this->intValue($row->attempts ?? null),
            maxAttempts: $this->intValue($row->max_attempts ?? null),
            providerKey: $this->nullableString($row->provider_key ?? null),
            providerMessageId: $this->nullableString($row->provider_message_id ?? null),
            lastErrorCode: $this->nullableString($row->last_error_code ?? null),
            lastErrorMessage: $this->nullableString($row->last_error_message ?? null),
            queuedAt: $this->dateTime($row->queued_at ?? null),
            sentAt: $this->nullableDateTime($row->sent_at ?? null),
            failedAt: $this->nullableDateTime($row->failed_at ?? null),
            canceledAt: $this->nullableDateTime($row->canceled_at ?? null),
            canceledReason: $this->nullableString($row->canceled_reason ?? null),
            lastAttemptAt: $this->nullableDateTime($row->last_attempt_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $this->normalizeArray($decoded) : [];
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->dateTime($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     */
    private function normalizeArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
