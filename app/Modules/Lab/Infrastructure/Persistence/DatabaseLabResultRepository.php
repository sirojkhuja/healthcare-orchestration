<?php

namespace App\Modules\Lab\Infrastructure\Persistence;

use App\Modules\Lab\Application\Contracts\LabResultRepository;
use App\Modules\Lab\Application\Data\LabResultData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseLabResultRepository implements LabResultRepository
{
    #[\Override]
    public function create(string $tenantId, string $orderId, array $attributes): LabResultData
    {
        $resultId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('lab_results')->insert([
            'id' => $resultId,
            'tenant_id' => $tenantId,
            'lab_order_id' => $orderId,
            'lab_test_id' => $attributes['lab_test_id'],
            'external_result_id' => $attributes['external_result_id'],
            'status' => $attributes['status'],
            'observed_at' => $attributes['observed_at'],
            'received_at' => $attributes['received_at'],
            'value_type' => $attributes['value_type'],
            'value_numeric' => $attributes['value_numeric'],
            'value_text' => $attributes['value_text'],
            'value_boolean' => $attributes['value_boolean'],
            'value_json' => $this->jsonValue($attributes['value_json']),
            'unit' => $attributes['unit'],
            'reference_range' => $attributes['reference_range'],
            'abnormal_flag' => $attributes['abnormal_flag'],
            'notes' => $attributes['notes'],
            'raw_payload' => $this->jsonValue($attributes['raw_payload']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInOrder($tenantId, $orderId, $resultId)
            ?? throw new \LogicException('Created lab result could not be reloaded.');
    }

    #[\Override]
    public function findInOrder(string $tenantId, string $orderId, string $resultId): ?LabResultData
    {
        $row = $this->baseQuery($tenantId, $orderId)
            ->where('id', $resultId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findInOrderByExternalId(string $tenantId, string $orderId, string $externalResultId): ?LabResultData
    {
        $row = $this->baseQuery($tenantId, $orderId)
            ->where('external_result_id', $externalResultId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForOrder(string $tenantId, string $orderId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId, $orderId)
            ->orderByDesc('observed_at')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $orderId, string $resultId, array $updates): ?LabResultData
    {
        if ($updates === []) {
            return $this->findInOrder($tenantId, $orderId, $resultId);
        }

        foreach (['value_json', 'raw_payload'] as $jsonKey) {
            if (array_key_exists($jsonKey, $updates)) {
                $updates[$jsonKey] = $this->jsonValue($updates[$jsonKey]);
            }
        }

        DB::table('lab_results')
            ->where('tenant_id', $tenantId)
            ->where('lab_order_id', $orderId)
            ->where('id', $resultId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInOrder($tenantId, $orderId, $resultId);
    }

    private function baseQuery(string $tenantId, string $orderId): Builder
    {
        return DB::table('lab_results')
            ->where('tenant_id', $tenantId)
            ->where('lab_order_id', $orderId);
    }

    private function boolValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
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

    private function toData(stdClass $row): LabResultData
    {
        return new LabResultData(
            resultId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            labOrderId: $this->stringValue($row->lab_order_id ?? null),
            labTestId: $this->nullableString($row->lab_test_id ?? null),
            externalResultId: $this->nullableString($row->external_result_id ?? null),
            status: $this->stringValue($row->status ?? null),
            observedAt: $this->dateTime($row->observed_at ?? null),
            receivedAt: $this->dateTime($row->received_at ?? null),
            valueType: $this->stringValue($row->value_type ?? null),
            valueNumeric: $this->nullableString($row->value_numeric ?? null),
            valueText: $this->nullableString($row->value_text ?? null),
            valueBoolean: $this->boolValue($row->value_boolean ?? null),
            valueJson: $this->jsonArray($row->value_json ?? null),
            unit: $this->nullableString($row->unit ?? null),
            referenceRange: $this->nullableString($row->reference_range ?? null),
            abnormalFlag: $this->nullableString($row->abnormal_flag ?? null),
            notes: $this->nullableString($row->notes ?? null),
            rawPayload: $this->jsonArray($row->raw_payload ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
