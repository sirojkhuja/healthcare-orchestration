<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationWebhookRepository;
use App\Modules\Integrations\Application\Data\InboundIntegrationWebhookData;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationWebhookRepository implements IntegrationWebhookRepository
{
    #[\Override]
    public function create(
        string $tenantId,
        string $integrationKey,
        string $name,
        string $endpointUrl,
        string $authMode,
        ?string $secret,
        ?string $secretHash,
        ?CarbonImmutable $secretLastRotatedAt,
        string $status,
        array $metadata,
        CarbonImmutable $now,
    ): IntegrationWebhookData {
        $id = (string) Str::uuid();

        DB::table('integration_webhooks')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'integration_key' => $integrationKey,
            'name' => $name,
            'endpoint_url' => $endpointUrl,
            'auth_mode' => $authMode,
            'secret' => $secret !== null ? Crypt::encryptString($secret) : null,
            'secret_hash' => $secretHash,
            'secret_last_rotated_at' => $secretLastRotatedAt,
            'status' => $status,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $integrationKey, $id)
            ?? throw new \LogicException('Integration webhook could not be reloaded after creation.');
    }

    #[\Override]
    public function delete(string $tenantId, string $integrationKey, string $webhookId): bool
    {
        return DB::table('integration_webhooks')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $webhookId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInboundTarget(string $integrationKey, string $webhookId): ?InboundIntegrationWebhookData
    {
        $row = DB::table('integration_webhooks')
            ->where('integration_key', $integrationKey)
            ->where('id', $webhookId)
            ->first();

        return $row instanceof stdClass ? $this->toInboundData($row) : null;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $integrationKey, string $webhookId): ?IntegrationWebhookData
    {
        $row = DB::table('integration_webhooks')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $webhookId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function list(string $tenantId, string $integrationKey): array
    {
        return array_values(array_map(
            fn (stdClass $row): IntegrationWebhookData => $this->toData($row),
            DB::table('integration_webhooks')
                ->where('tenant_id', $tenantId)
                ->where('integration_key', $integrationKey)
                ->orderBy('name')
                ->get()
                ->all(),
        ));
    }

    #[\Override]
    public function updateSecret(
        string $tenantId,
        string $integrationKey,
        string $webhookId,
        ?string $secret,
        ?string $secretHash,
        ?CarbonImmutable $secretLastRotatedAt,
        CarbonImmutable $updatedAt,
    ): ?IntegrationWebhookData {
        $updated = DB::table('integration_webhooks')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $webhookId)
            ->update([
                'secret' => $secret !== null ? Crypt::encryptString($secret) : null,
                'secret_hash' => $secretHash,
                'secret_last_rotated_at' => $secretLastRotatedAt,
                'updated_at' => $updatedAt,
            ]);

        return $updated > 0 ? $this->findInTenant($tenantId, $integrationKey, $webhookId) : null;
    }

    private function toData(stdClass $row): IntegrationWebhookData
    {
        $metadata = $this->decodedArray($row->metadata ?? null);

        return new IntegrationWebhookData(
            id: $this->stringValue($row->id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            name: $this->stringValue($row->name ?? null),
            endpointUrl: $this->stringValue($row->endpoint_url ?? null),
            authMode: $this->stringValue($row->auth_mode ?? null),
            status: $this->stringValue($row->status ?? null),
            secretConfigured: $this->nullableString($row->secret_hash ?? null) !== null,
            rotateSupported: false,
            secretPlaintext: null,
            metadata: $this->metadata($metadata),
            secretLastRotatedAt: $this->nullableDateTime($row->secret_last_rotated_at ?? null),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    private function toInboundData(stdClass $row): InboundIntegrationWebhookData
    {
        return new InboundIntegrationWebhookData(
            id: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            name: $this->stringValue($row->name ?? null),
            status: $this->stringValue($row->status ?? null),
            secretHash: $this->nullableString($row->secret_hash ?? null),
            metadata: $this->metadata($this->decodedArray($row->metadata ?? null)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $metadata */
        $metadata = array_filter(
            $value,
            static fn (mixed $_item, mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );

        return $metadata;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodedArray(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? CarbonImmutable::parse($normalized) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
