<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\EmailProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEmailProviderSettingsRepository implements EmailProviderSettingsRepository
{
    #[\Override]
    public function get(string $tenantId): EmailProviderSettingsData
    {
        $row = DB::table('notification_email_settings')
            ->where('tenant_id', $tenantId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : $this->defaultData($tenantId);
    }

    #[\Override]
    public function save(string $tenantId, array $attributes): EmailProviderSettingsData
    {
        $existing = DB::table('notification_email_settings')
            ->where('tenant_id', $tenantId)
            ->first(['id', 'created_at']);
        $current = $this->get($tenantId);
        $now = CarbonImmutable::now();

        DB::table('notification_email_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) && $existing->id !== ''
                    ? $existing->id
                    : (string) Str::uuid(),
                'enabled' => $attributes['enabled'] ?? $current->enabled,
                'provider_key' => $attributes['provider_key'] ?? $current->providerKey,
                'from_address' => $attributes['from_address'] ?? $current->fromAddress,
                'from_name' => $attributes['from_name'] ?? $current->fromName,
                'reply_to_address' => array_key_exists('reply_to_address', $attributes)
                    ? $attributes['reply_to_address']
                    : $current->replyToAddress,
                'reply_to_name' => array_key_exists('reply_to_name', $attributes)
                    ? $attributes['reply_to_name']
                    : $current->replyToName,
                'updated_at' => $now,
                'created_at' => is_object($existing) && isset($existing->created_at) ? $existing->created_at : $now,
            ],
        );

        return $this->get($tenantId);
    }

    private function defaultData(string $tenantId): EmailProviderSettingsData
    {
        $now = CarbonImmutable::now();
        $enabled = filter_var(config('notifications.email.enabled_by_default', false), FILTER_VALIDATE_BOOL);

        return new EmailProviderSettingsData(
            tenantId: $tenantId,
            enabled: $enabled,
            providerKey: config()->string('notifications.email.provider_key', 'email'),
            fromAddress: config()->string('notifications.email.default_from_address', 'noreply@medflow.local'),
            fromName: config()->string('notifications.email.default_from_name', 'MedFlow'),
            replyToAddress: $this->nullableString(config('notifications.email.default_reply_to_address')),
            replyToName: $this->nullableString(config('notifications.email.default_reply_to_name')),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function toData(stdClass $row): EmailProviderSettingsData
    {
        return new EmailProviderSettingsData(
            tenantId: $this->stringValue($row->tenant_id ?? null),
            enabled: (bool) ($row->enabled ?? false),
            providerKey: $this->stringValue($row->provider_key ?? null) ?: 'email',
            fromAddress: $this->stringValue($row->from_address ?? null) ?: 'noreply@medflow.local',
            fromName: $this->stringValue($row->from_name ?? null) ?: 'MedFlow',
            replyToAddress: $this->nullableString($row->reply_to_address ?? null),
            replyToName: $this->nullableString($row->reply_to_name ?? null),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
