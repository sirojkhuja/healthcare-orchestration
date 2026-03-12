<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Data\IntegrationLogData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationLogRepository implements IntegrationLogRepository
{
    #[\Override]
    public function create(
        string $tenantId,
        string $integrationKey,
        string $level,
        string $event,
        string $message,
        array $context,
        CarbonImmutable $createdAt,
    ): IntegrationLogData {
        $id = (string) Str::uuid();

        DB::table('integration_logs')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'integration_key' => $integrationKey,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);

        return new IntegrationLogData(
            id: $id,
            integrationKey: $integrationKey,
            level: $level,
            event: $event,
            message: $message,
            context: $context,
            createdAt: $createdAt,
        );
    }

    #[\Override]
    public function list(
        string $tenantId,
        string $integrationKey,
        ?string $level = null,
        ?string $event = null,
        int $limit = 50,
    ): array {
        $query = DB::table('integration_logs')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 100)));

        if (is_string($level) && trim($level) !== '') {
            $query->where('level', trim($level));
        }

        if (is_string($event) && trim($event) !== '') {
            $query->where('event', trim($event));
        }

        return array_values(array_map(
            fn (stdClass $row): IntegrationLogData => $this->toData($row),
            $query->get()->all(),
        ));
    }

    private function toData(stdClass $row): IntegrationLogData
    {
        $context = $this->decodedArray($row->context ?? null);

        return new IntegrationLogData(
            id: $this->stringValue($row->id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            level: $this->stringValue($row->level ?? null),
            event: $this->stringValue($row->event ?? null),
            message: $this->stringValue($row->message ?? null),
            context: $this->context($context),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function context(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $context */
        $context = array_filter(
            $value,
            static fn (mixed $_item, mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );

        return $context;
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
        return is_string($value) ? trim($value) : '';
    }
}
