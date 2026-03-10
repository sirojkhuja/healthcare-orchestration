<?php

namespace App\Modules\Lab\Infrastructure\Persistence;

use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseLabOrderRepository implements LabOrderRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): LabOrderData
    {
        $orderId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('lab_orders')->insert([
            'id' => $orderId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'encounter_id' => $attributes['encounter_id'],
            'treatment_item_id' => $attributes['treatment_item_id'],
            'lab_test_id' => $attributes['lab_test_id'],
            'lab_provider_key' => $attributes['lab_provider_key'],
            'requested_test_code' => $attributes['requested_test_code'],
            'requested_test_name' => $attributes['requested_test_name'],
            'requested_specimen_type' => $attributes['requested_specimen_type'],
            'requested_result_type' => $attributes['requested_result_type'],
            'status' => $attributes['status'],
            'ordered_at' => $attributes['ordered_at'],
            'timezone' => $attributes['timezone'],
            'notes' => $attributes['notes'],
            'external_order_id' => $attributes['external_order_id'],
            'sent_at' => $attributes['sent_at'],
            'specimen_collected_at' => $attributes['specimen_collected_at'],
            'specimen_received_at' => $attributes['specimen_received_at'],
            'completed_at' => $attributes['completed_at'],
            'canceled_at' => $attributes['canceled_at'],
            'cancel_reason' => $attributes['cancel_reason'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $orderId)
            ?? throw new \LogicException('Created lab order could not be reloaded.');
    }

    #[\Override]
    public function findByExternalOrderId(string $labProviderKey, string $externalOrderId): ?LabOrderData
    {
        $row = $this->baseQuery(null)
            ->where('lab_orders.lab_provider_key', $labProviderKey)
            ->where('lab_orders.external_order_id', $externalOrderId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $orderId, bool $withDeleted = false): ?LabOrderData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('lab_orders.id', $orderId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findManyInTenant(string $tenantId, array $orderIds, bool $withDeleted = false): array
    {
        if ($orderIds === []) {
            return [];
        }

        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId, $withDeleted)
            ->whereIn('lab_orders.id', $orderIds)
            ->orderByDesc('lab_orders.ordered_at')
            ->orderByDesc('lab_orders.created_at')
            ->orderByDesc('lab_orders.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function search(string $tenantId, LabOrderSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);

        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
            'provider_id' => $criteria->providerId,
            'encounter_id' => $criteria->encounterId,
            'lab_test_id' => $criteria->labTestId,
            'lab_provider_key' => $criteria->labProviderKey,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('lab_orders.'.$column, $value);
            }
        }

        if ($criteria->orderedFrom !== null) {
            $query->where('lab_orders.ordered_at', '>=', CarbonImmutable::parse($criteria->orderedFrom)->startOfDay());
        }

        if ($criteria->orderedTo !== null) {
            $query->where('lab_orders.ordered_at', '<=', CarbonImmutable::parse($criteria->orderedTo)->endOfDay());
        }

        if ($criteria->createdFrom !== null) {
            $query->where('lab_orders.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('lab_orders.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(CAST(lab_orders.id AS TEXT)) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(lab_orders.external_order_id, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, patients.first_name, \'\') || \' \' || patients.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.preferred_name, providers.first_name, \'\') || \' \' || providers.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(lab_orders.requested_test_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(lab_orders.requested_test_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(lab_orders.notes, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('lab_orders.ordered_at')
            ->orderByDesc('lab_orders.created_at')
            ->orderByDesc('lab_orders.id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listForReconciliation(
        string $tenantId,
        string $labProviderKey,
        array $statuses,
        int $limit,
        array $orderIds = [],
    ): array {
        $query = $this->baseQuery($tenantId)
            ->where('lab_orders.lab_provider_key', $labProviderKey)
            ->whereIn('lab_orders.status', $statuses);

        if ($orderIds !== []) {
            $query->whereIn('lab_orders.id', $orderIds);
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('lab_orders.ordered_at')
            ->orderByDesc('lab_orders.created_at')
            ->orderByDesc('lab_orders.id')
            ->limit($limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $orderId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('lab_orders')
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $orderId, array $updates): ?LabOrderData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $orderId);
        }

        if (array_key_exists('last_transition', $updates)) {
            $updates['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        DB::table('lab_orders')
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->whereNull('deleted_at')
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $orderId);
    }

    private function baseQuery(?string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('lab_orders')
            ->join('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'lab_orders.patient_id')
                    ->on('patients.tenant_id', '=', 'lab_orders.tenant_id');
            })
            ->join('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'lab_orders.provider_id')
                    ->on('providers.tenant_id', '=', 'lab_orders.tenant_id');
            })
            ->leftJoin('encounters', function (JoinClause $join): void {
                $join->on('encounters.id', '=', 'lab_orders.encounter_id')
                    ->on('encounters.tenant_id', '=', 'lab_orders.tenant_id');
            })
            ->leftJoin('treatment_plan_items', function (JoinClause $join): void {
                $join->on('treatment_plan_items.id', '=', 'lab_orders.treatment_item_id')
                    ->on('treatment_plan_items.tenant_id', '=', 'lab_orders.tenant_id');
            })
            ->select([
                'lab_orders.id as order_id',
                'lab_orders.tenant_id',
                'lab_orders.patient_id',
                'lab_orders.provider_id',
                'lab_orders.encounter_id',
                'lab_orders.treatment_item_id',
                'lab_orders.lab_test_id',
                'lab_orders.lab_provider_key',
                'lab_orders.requested_test_code',
                'lab_orders.requested_test_name',
                'lab_orders.requested_specimen_type',
                'lab_orders.requested_result_type',
                'lab_orders.status',
                'lab_orders.ordered_at',
                'lab_orders.timezone',
                'lab_orders.notes',
                'lab_orders.external_order_id',
                'lab_orders.sent_at',
                'lab_orders.specimen_collected_at',
                'lab_orders.specimen_received_at',
                'lab_orders.completed_at',
                'lab_orders.canceled_at',
                'lab_orders.cancel_reason',
                'lab_orders.last_transition',
                'lab_orders.deleted_at',
                'lab_orders.created_at',
                'lab_orders.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                DB::raw('COALESCE(encounters.summary, encounters.chief_complaint) as encounter_summary'),
                'treatment_plan_items.title as treatment_item_title',
                DB::raw('(select count(*) from lab_results where lab_results.lab_order_id = lab_orders.id and lab_results.tenant_id = lab_orders.tenant_id) as result_count'),
            ]);

        if ($tenantId !== null) {
            $query->where('lab_orders.tenant_id', $tenantId);
        }

        if (! $withDeleted) {
            $query->whereNull('lab_orders.deleted_at');
        }

        return $query;
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

    private function displayName(?string $preferredName, ?string $firstName, ?string $lastName, string $fallback): string
    {
        $parts = array_values(array_filter([
            $preferredName ?? $firstName,
            $lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallback;
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

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toData(stdClass $row): LabOrderData
    {
        return new LabOrderData(
            orderId: $this->stringValue($row->order_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->displayName(
                $this->nullableString($row->patient_preferred_name ?? null),
                $this->nullableString($row->patient_first_name ?? null),
                $this->nullableString($row->patient_last_name ?? null),
                $this->stringValue($row->patient_id ?? null),
            ),
            providerId: $this->stringValue($row->provider_id ?? null),
            providerDisplayName: $this->displayName(
                $this->nullableString($row->provider_preferred_name ?? null),
                $this->nullableString($row->provider_first_name ?? null),
                $this->nullableString($row->provider_last_name ?? null),
                $this->stringValue($row->provider_id ?? null),
            ),
            encounterId: $this->nullableString($row->encounter_id ?? null),
            encounterSummary: $this->nullableString($row->encounter_summary ?? null),
            treatmentItemId: $this->nullableString($row->treatment_item_id ?? null),
            treatmentItemTitle: $this->nullableString($row->treatment_item_title ?? null),
            labTestId: $this->nullableString($row->lab_test_id ?? null),
            labProviderKey: $this->stringValue($row->lab_provider_key ?? null),
            requestedTestCode: $this->stringValue($row->requested_test_code ?? null),
            requestedTestName: $this->stringValue($row->requested_test_name ?? null),
            requestedSpecimenType: $this->stringValue($row->requested_specimen_type ?? null),
            requestedResultType: $this->stringValue($row->requested_result_type ?? null),
            status: $this->stringValue($row->status ?? null),
            orderedAt: $this->dateTime($row->ordered_at ?? null),
            timezone: $this->stringValue($row->timezone ?? null),
            notes: $this->nullableString($row->notes ?? null),
            externalOrderId: $this->nullableString($row->external_order_id ?? null),
            sentAt: $this->nullableDateTime($row->sent_at ?? null),
            specimenCollectedAt: $this->nullableDateTime($row->specimen_collected_at ?? null),
            specimenReceivedAt: $this->nullableDateTime($row->specimen_received_at ?? null),
            completedAt: $this->nullableDateTime($row->completed_at ?? null),
            canceledAt: $this->nullableDateTime($row->canceled_at ?? null),
            cancelReason: $this->nullableString($row->cancel_reason ?? null),
            lastTransition: $this->jsonArray($row->last_transition ?? null),
            resultCount: $this->intValue($row->result_count ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
