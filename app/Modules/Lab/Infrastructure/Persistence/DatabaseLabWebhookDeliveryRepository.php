<?php

namespace App\Modules\Lab\Infrastructure\Persistence;

use App\Modules\Lab\Application\Contracts\LabWebhookDeliveryRepository;
use App\Modules\Lab\Application\Data\LabWebhookDeliveryData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseLabWebhookDeliveryRepository implements LabWebhookDeliveryRepository
{
    #[\Override]
    public function create(array $attributes): LabWebhookDeliveryData
    {
        $deliveryRecordId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('lab_webhook_deliveries')->insert([
            'id' => $deliveryRecordId,
            'provider_key' => $attributes['provider_key'],
            'delivery_id' => $attributes['delivery_id'],
            'payload_hash' => $attributes['payload_hash'],
            'signature_hash' => $attributes['signature_hash'],
            'lab_order_id' => $attributes['lab_order_id'],
            'resolved_tenant_id' => $attributes['resolved_tenant_id'],
            'outcome' => $attributes['outcome'],
            'occurred_at' => $attributes['occurred_at'],
            'processed_at' => $attributes['processed_at'],
            'error_message' => $attributes['error_message'],
            'payload' => $this->jsonValue($attributes['payload']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByProviderAndDeliveryId($attributes['provider_key'], $attributes['delivery_id'])
            ?? throw new \LogicException('Created lab webhook delivery could not be reloaded.');
    }

    #[\Override]
    public function findByProviderAndDeliveryId(string $providerKey, string $deliveryId): ?LabWebhookDeliveryData
    {
        $row = DB::table('lab_webhook_deliveries')
            ->where('provider_key', $providerKey)
            ->where('delivery_id', $deliveryId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
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

    /**
     * @return array<string, mixed>|null
     */
    private function jsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->normalizeAssocArray($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->normalizeAssocArray($decoded) : null;
    }

    private function jsonValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
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
        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(array $value): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(stdClass $row): LabWebhookDeliveryData
    {
        return new LabWebhookDeliveryData(
            deliveryRecordId: $this->stringValue($row->id ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            deliveryId: $this->stringValue($row->delivery_id ?? null),
            payloadHash: $this->stringValue($row->payload_hash ?? null),
            signatureHash: $this->stringValue($row->signature_hash ?? null),
            labOrderId: $this->nullableString($row->lab_order_id ?? null),
            resolvedTenantId: $this->nullableString($row->resolved_tenant_id ?? null),
            outcome: $this->stringValue($row->outcome ?? null),
            occurredAt: $this->nullableDateTime($row->occurred_at ?? null),
            processedAt: $this->nullableDateTime($row->processed_at ?? null),
            errorMessage: $this->nullableString($row->error_message ?? null),
            payload: $this->jsonArray($row->payload ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
