<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\TelegramWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\TelegramWebhookDeliveryData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseTelegramWebhookDeliveryRepository implements TelegramWebhookDeliveryRepository
{
    #[\Override]
    public function create(array $attributes): TelegramWebhookDeliveryData
    {
        $deliveryRecordId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('telegram_webhook_deliveries')->insert([
            'id' => $deliveryRecordId,
            'provider_key' => $attributes['provider_key'],
            'update_id' => $attributes['update_id'],
            'event_type' => $attributes['event_type'],
            'chat_id' => $attributes['chat_id'],
            'message_id' => $attributes['message_id'],
            'resolved_tenant_id' => $attributes['resolved_tenant_id'],
            'payload_hash' => $attributes['payload_hash'],
            'secret_hash' => $attributes['secret_hash'],
            'outcome' => $attributes['outcome'],
            'error_code' => $attributes['error_code'],
            'error_message' => $attributes['error_message'],
            'processed_at' => $attributes['processed_at'],
            'payload' => json_encode($attributes['payload'], JSON_THROW_ON_ERROR),
            'response' => json_encode($attributes['response'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($deliveryRecordId)
            ?? throw new \LogicException('Created Telegram webhook delivery could not be reloaded.');
    }

    #[\Override]
    public function findByUpdateId(string $providerKey, string $updateId): ?TelegramWebhookDeliveryData
    {
        $row = DB::table('telegram_webhook_deliveries')
            ->where('provider_key', $providerKey)
            ->where('update_id', $updateId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    private function findById(string $deliveryRecordId): ?TelegramWebhookDeliveryData
    {
        $row = DB::table('telegram_webhook_deliveries')
            ->where('id', $deliveryRecordId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
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

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            $normalized = [];

            /** @psalm-suppress MixedAssignment */
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $normalized[$key] = $item;
                }
            }

            return $normalized;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->jsonArray($decoded) : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function toData(stdClass $row): TelegramWebhookDeliveryData
    {
        return new TelegramWebhookDeliveryData(
            deliveryRecordId: $this->stringValue($row->id ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            updateId: $this->stringValue($row->update_id ?? null),
            eventType: $this->stringValue($row->event_type ?? null),
            chatId: $this->nullableString($row->chat_id ?? null),
            messageId: $this->nullableString($row->message_id ?? null),
            resolvedTenantId: $this->nullableString($row->resolved_tenant_id ?? null),
            payloadHash: $this->stringValue($row->payload_hash ?? null),
            secretHash: $this->stringValue($row->secret_hash ?? null),
            outcome: $this->stringValue($row->outcome ?? null),
            errorCode: $this->nullableString($row->error_code ?? null),
            errorMessage: $this->nullableString($row->error_message ?? null),
            processedAt: $this->nullableDateTime($row->processed_at ?? null),
            payload: $this->jsonArray($row->payload ?? null),
            response: $this->jsonArray($row->response ?? null),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }
}
