<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationPluginWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\IntegrationPluginWebhookDeliveryData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationPluginWebhookDeliveryRepository implements IntegrationPluginWebhookDeliveryRepository
{
    #[\Override]
    public function create(array $attributes): IntegrationPluginWebhookDeliveryData
    {
        $id = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('integration_plugin_webhook_deliveries')->insert([
            'id' => $id,
            'integration_key' => $attributes['integration_key'],
            'webhook_id' => $attributes['webhook_id'],
            'resolved_tenant_id' => $attributes['resolved_tenant_id'],
            'delivery_id' => $attributes['delivery_id'],
            'provider_reference' => $attributes['provider_reference'],
            'event_type' => $attributes['event_type'],
            'payload_hash' => $attributes['payload_hash'],
            'secret_hash' => $attributes['secret_hash'],
            'outcome' => $attributes['outcome'],
            'error_code' => $attributes['error_code'],
            'error_message' => $attributes['error_message'],
            'processed_at' => $attributes['processed_at'],
            'payload' => $this->jsonValue($attributes['payload']),
            'response' => $this->jsonValue($attributes['response']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($id)
            ?? throw new \LogicException('Created integration plug-in webhook delivery could not be reloaded.');
    }

    #[\Override]
    public function findByReplayKey(
        string $integrationKey,
        string $webhookId,
        string $deliveryId,
    ): ?IntegrationPluginWebhookDeliveryData {
        $row = DB::table('integration_plugin_webhook_deliveries')
            ->where('integration_key', $integrationKey)
            ->where('webhook_id', $webhookId)
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

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    private function findById(string $id): ?IntegrationPluginWebhookDeliveryData
    {
        $row = DB::table('integration_plugin_webhook_deliveries')
            ->where('id', $id)
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

    private function toData(stdClass $row): IntegrationPluginWebhookDeliveryData
    {
        return new IntegrationPluginWebhookDeliveryData(
            id: $this->stringValue($row->id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            webhookId: $this->stringValue($row->webhook_id ?? null),
            resolvedTenantId: $this->stringValue($row->resolved_tenant_id ?? null),
            deliveryId: $this->stringValue($row->delivery_id ?? null),
            providerReference: $this->nullableString($row->provider_reference ?? null),
            eventType: $this->stringValue($row->event_type ?? null),
            payloadHash: $this->stringValue($row->payload_hash ?? null),
            secretHash: $this->stringValue($row->secret_hash ?? null),
            outcome: $this->stringValue($row->outcome ?? null),
            errorCode: $this->nullableString($row->error_code ?? null),
            errorMessage: $this->nullableString($row->error_message ?? null),
            processedAt: $this->nullableDateTime($row->processed_at ?? null),
            payload: $this->jsonArray($row->payload ?? null),
            response: $this->jsonArray($row->response ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
