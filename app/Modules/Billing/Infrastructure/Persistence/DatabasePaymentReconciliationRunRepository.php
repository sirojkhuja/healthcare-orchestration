<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\PaymentReconciliationRunRepository;
use App\Modules\Billing\Application\Data\PaymentReconciliationResultData;
use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePaymentReconciliationRunRepository implements PaymentReconciliationRunRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): PaymentReconciliationRunData
    {
        $runId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('payment_reconciliation_runs')->insert([
            'id' => $runId,
            'tenant_id' => $tenantId,
            'provider_key' => $attributes['provider_key'],
            'requested_payment_ids' => $this->jsonValue($attributes['requested_payment_ids']),
            'scanned_count' => $attributes['scanned_count'],
            'changed_count' => $attributes['changed_count'],
            'result_count' => $attributes['result_count'],
            'results' => $this->jsonValue($attributes['results']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $runId)
            ?? throw new \LogicException('Created payment reconciliation run could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $runId): ?PaymentReconciliationRunData
    {
        $row = DB::table('payment_reconciliation_runs')
            ->where('tenant_id', $tenantId)
            ->where('id', $runId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listInTenant(string $tenantId, ?string $providerKey = null, int $limit = 25): array
    {
        $query = DB::table('payment_reconciliation_runs')
            ->where('tenant_id', $tenantId);

        if (is_string($providerKey) && trim($providerKey) !== '') {
            $query->where('provider_key', trim($providerKey));
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): PaymentReconciliationRunData => $this->toData($row),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(
                fn (array $item): array => $this->normalizeAssocArray($item),
                array_values(array_filter($value, static fn (mixed $item): bool => is_array($item))),
            );
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_map(
                fn (array $item): array => $this->normalizeAssocArray($item),
                array_values(array_filter($decoded, static fn (mixed $item): bool => is_array($item))),
            )
            : [];
    }

    private function jsonValue(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonAssocArray(mixed $value): array
    {
        return is_array($value) ? $this->normalizeAssocArray($value) : [];
    }

    private function nullableStringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, static fn (mixed $item): bool => is_string($item)))
            : [];
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

    private function toData(stdClass $row): PaymentReconciliationRunData
    {
        return new PaymentReconciliationRunData(
            runId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            requestedPaymentIds: $this->stringList($row->requested_payment_ids ?? null),
            scannedCount: $this->intValue($row->scanned_count ?? null),
            changedCount: $this->intValue($row->changed_count ?? null),
            resultCount: $this->intValue($row->result_count ?? null),
            results: array_map(
                fn (array $result): PaymentReconciliationResultData => new PaymentReconciliationResultData(
                    paymentId: $this->stringValue($result['payment_id'] ?? null),
                    statusBefore: $this->stringValue($result['status_before'] ?? null),
                    statusAfter: $this->stringValue($result['status_after'] ?? null),
                    changed: (bool) ($result['changed'] ?? false),
                    providerPaymentId: $this->nullableStringValue($result['provider_payment_id'] ?? null),
                    providerStatus: $this->nullableStringValue($result['provider_status'] ?? null),
                    failureCode: $this->nullableStringValue($result['failure_code'] ?? null),
                    failureMessage: $this->nullableStringValue($result['failure_message'] ?? null),
                    payment: $this->jsonAssocArray($result['payment'] ?? null),
                ),
                $this->jsonList($row->results ?? null),
            ),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
