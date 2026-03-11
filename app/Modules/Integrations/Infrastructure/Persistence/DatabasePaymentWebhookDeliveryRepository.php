<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePaymentWebhookDeliveryRepository implements PaymentWebhookDeliveryRepository
{
    #[\Override]
    public function create(array $attributes): PaymentWebhookDeliveryData
    {
        $deliveryRecordId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('payment_webhook_deliveries')->insert([
            'id' => $deliveryRecordId,
            'provider_key' => $attributes['provider_key'],
            'method' => $attributes['method'],
            'replay_key' => $attributes['replay_key'],
            'provider_transaction_id' => $attributes['provider_transaction_id'],
            'request_id' => $attributes['request_id'],
            'payment_id' => $attributes['payment_id'],
            'resolved_tenant_id' => $attributes['resolved_tenant_id'],
            'payload_hash' => $attributes['payload_hash'],
            'auth_hash' => $attributes['auth_hash'],
            'provider_time_millis' => $attributes['provider_time_millis'],
            'outcome' => $attributes['outcome'],
            'provider_error_code' => $attributes['provider_error_code'],
            'provider_error_message' => $attributes['provider_error_message'],
            'processed_at' => $attributes['processed_at'],
            'payload' => $this->jsonValue($attributes['payload']),
            'response' => $this->jsonValue($attributes['response']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($deliveryRecordId)
            ?? throw new \LogicException('Created payment webhook delivery could not be reloaded.');
    }

    #[\Override]
    public function findByReplayKey(string $providerKey, string $method, string $replayKey): ?PaymentWebhookDeliveryData
    {
        $row = DB::table('payment_webhook_deliveries')
            ->where('provider_key', $providerKey)
            ->where('method', $method)
            ->where('replay_key', $replayKey)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listByProviderMethodAndTimeRange(
        string $providerKey,
        string $method,
        int $fromMillis,
        int $toMillis,
    ): array {
        /** @var list<stdClass> $rows */
        $rows = DB::table('payment_webhook_deliveries')
            ->where('provider_key', $providerKey)
            ->where('method', $method)
            ->where('outcome', 'processed')
            ->whereNotNull('provider_time_millis')
            ->whereBetween('provider_time_millis', [$fromMillis, $toMillis])
            ->orderBy('provider_time_millis')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): PaymentWebhookDeliveryData => $this->toData($row),
            $rows,
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

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    private function findById(string $deliveryRecordId): ?PaymentWebhookDeliveryData
    {
        $row = DB::table('payment_webhook_deliveries')
            ->where('id', $deliveryRecordId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
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
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
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

    private function toData(stdClass $row): PaymentWebhookDeliveryData
    {
        return new PaymentWebhookDeliveryData(
            deliveryRecordId: $this->stringValue($row->id ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            method: $this->stringValue($row->method ?? null),
            replayKey: $this->nullableString($row->replay_key ?? null),
            providerTransactionId: $this->nullableString($row->provider_transaction_id ?? null),
            requestId: $this->nullableString($row->request_id ?? null),
            paymentId: $this->nullableString($row->payment_id ?? null),
            resolvedTenantId: $this->nullableString($row->resolved_tenant_id ?? null),
            payloadHash: $this->stringValue($row->payload_hash ?? null),
            authHash: $this->stringValue($row->auth_hash ?? null),
            providerTimeMillis: $this->nullableInt($row->provider_time_millis ?? null),
            outcome: $this->stringValue($row->outcome ?? null),
            providerErrorCode: $this->nullableString($row->provider_error_code ?? null),
            providerErrorMessage: $this->nullableString($row->provider_error_message ?? null),
            processedAt: $this->nullableDateTime($row->processed_at ?? null),
            payload: $this->jsonArray($row->payload ?? null),
            response: $this->jsonArray($row->response ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
