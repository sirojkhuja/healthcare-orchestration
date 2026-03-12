<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationStateRepository;
use App\Modules\Integrations\Application\Data\IntegrationStateData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationStateRepository implements IntegrationStateRepository
{
    #[\Override]
    public function get(string $tenantId, string $integrationKey): ?IntegrationStateData
    {
        $row = DB::table('integration_states')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function saveEnabled(
        string $tenantId,
        string $integrationKey,
        bool $enabled,
        CarbonImmutable $now,
    ): IntegrationStateData {
        $existing = DB::table('integration_states')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->first(['id', 'created_at', 'last_test_status', 'last_test_message', 'last_tested_at']);

        DB::table('integration_states')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'integration_key' => $integrationKey,
            ],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) ? $existing->id : (string) Str::uuid(),
                'enabled' => $enabled,
                'last_test_status' => is_object($existing) ? $existing->last_test_status : null,
                'last_test_message' => is_object($existing) ? $existing->last_test_message : null,
                'last_tested_at' => is_object($existing) ? $existing->last_tested_at : null,
                'created_at' => is_object($existing) && isset($existing->created_at) ? $existing->created_at : $now,
                'updated_at' => $now,
            ],
        );

        return $this->get($tenantId, $integrationKey)
            ?? throw new \LogicException('Integration state could not be reloaded after save.');
    }

    #[\Override]
    public function saveTestResult(
        string $tenantId,
        string $integrationKey,
        string $status,
        ?string $message,
        CarbonImmutable $testedAt,
    ): IntegrationStateData {
        $existing = DB::table('integration_states')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->first(['id', 'created_at', 'enabled']);

        DB::table('integration_states')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'integration_key' => $integrationKey,
            ],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) ? $existing->id : (string) Str::uuid(),
                'enabled' => is_object($existing) ? (bool) ($existing->enabled ?? false) : false,
                'last_test_status' => $status,
                'last_test_message' => $message,
                'last_tested_at' => $testedAt,
                'created_at' => is_object($existing) && isset($existing->created_at) ? $existing->created_at : $testedAt,
                'updated_at' => $testedAt,
            ],
        );

        return $this->get($tenantId, $integrationKey)
            ?? throw new \LogicException('Integration state could not be reloaded after test-result save.');
    }

    private function toData(stdClass $row): IntegrationStateData
    {
        return new IntegrationStateData(
            integrationKey: $this->stringValue($row->integration_key ?? null),
            enabled: (bool) ($row->enabled ?? false),
            lastTestStatus: $this->nullableString($row->last_test_status ?? null),
            lastTestMessage: $this->nullableString($row->last_test_message ?? null),
            lastTestedAt: $this->nullableDateTime($row->last_tested_at ?? null),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
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
