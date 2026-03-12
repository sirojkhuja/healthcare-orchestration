<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationCredentialRepository;
use App\Modules\Integrations\Application\Data\StoredIntegrationCredentialsData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationCredentialRepository implements IntegrationCredentialRepository
{
    #[\Override]
    public function delete(string $tenantId, string $integrationKey): bool
    {
        return DB::table('integration_credentials')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->delete() > 0;
    }

    #[\Override]
    public function get(string $tenantId, string $integrationKey): ?StoredIntegrationCredentialsData
    {
        $row = DB::table('integration_credentials')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function save(
        string $tenantId,
        string $integrationKey,
        array $values,
        array $configuredFields,
        CarbonImmutable $now,
    ): StoredIntegrationCredentialsData {
        $existing = DB::table('integration_credentials')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->first(['id', 'created_at']);

        DB::table('integration_credentials')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'integration_key' => $integrationKey,
            ],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) ? $existing->id : (string) Str::uuid(),
                'credential_payload' => Crypt::encryptString(json_encode($values, JSON_THROW_ON_ERROR)),
                'configured_fields' => json_encode($configuredFields, JSON_THROW_ON_ERROR),
                'created_at' => is_object($existing) && isset($existing->created_at) ? $existing->created_at : $now,
                'updated_at' => $now,
            ],
        );

        return $this->get($tenantId, $integrationKey)
            ?? throw new \LogicException('Integration credentials could not be reloaded after save.');
    }

    private function toData(stdClass $row): StoredIntegrationCredentialsData
    {
        /** @var mixed $decoded */
        $decoded = json_decode(Crypt::decryptString($this->stringValue($row->credential_payload ?? null)), true);
        $values = is_array($decoded) ? $this->stringMap($decoded) : [];
        $configuredFields = $this->decodedArray($row->configured_fields ?? null);

        return new StoredIntegrationCredentialsData(
            integrationKey: $this->stringValue($row->integration_key ?? null),
            values: $values,
            configuredFields: array_values(array_filter($configuredFields, static fn (mixed $value): bool => is_string($value))),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, string|null>
     */
    private function stringMap(array $values): array
    {
        $result = [];

        foreach (array_keys($values) as $key) {
            if (! is_string($key)) {
                continue;
            }

            if (($values[$key] ?? null) === null) {
                $result[$key] = null;

                continue;
            }

            if (is_string($values[$key])) {
                $normalized = trim($values[$key]);
                $result[$key] = $normalized !== '' ? $normalized : null;
            }
        }

        return $result;
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

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
