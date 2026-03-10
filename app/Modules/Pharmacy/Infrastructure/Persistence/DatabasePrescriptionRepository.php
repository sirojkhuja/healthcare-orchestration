<?php

namespace App\Modules\Pharmacy\Infrastructure\Persistence;

use App\Modules\Pharmacy\Application\Contracts\PrescriptionRepository;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Data\PrescriptionSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePrescriptionRepository implements PrescriptionRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): PrescriptionData
    {
        $prescriptionId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('prescriptions')->insert([
            'id' => $prescriptionId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'encounter_id' => $attributes['encounter_id'],
            'treatment_item_id' => $attributes['treatment_item_id'],
            'medication_name' => $attributes['medication_name'],
            'medication_code' => $attributes['medication_code'],
            'dosage' => $attributes['dosage'],
            'route' => $attributes['route'],
            'frequency' => $attributes['frequency'],
            'quantity' => $attributes['quantity'],
            'quantity_unit' => $attributes['quantity_unit'],
            'authorized_refills' => $attributes['authorized_refills'],
            'instructions' => $attributes['instructions'],
            'notes' => $attributes['notes'],
            'starts_on' => $attributes['starts_on'],
            'ends_on' => $attributes['ends_on'],
            'status' => $attributes['status'],
            'issued_at' => $attributes['issued_at'],
            'dispensed_at' => $attributes['dispensed_at'],
            'canceled_at' => $attributes['canceled_at'],
            'cancel_reason' => $attributes['cancel_reason'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $prescriptionId)
            ?? throw new \LogicException('Created prescription could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $prescriptionId, bool $withDeleted = false): ?PrescriptionData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('prescriptions.id', $prescriptionId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function search(string $tenantId, PrescriptionSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);

        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
            'provider_id' => $criteria->providerId,
            'encounter_id' => $criteria->encounterId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('prescriptions.'.$column, $value);
            }
        }

        if ($criteria->issuedFrom !== null) {
            $query->where('prescriptions.issued_at', '>=', CarbonImmutable::parse($criteria->issuedFrom)->startOfDay());
        }

        if ($criteria->issuedTo !== null) {
            $query->where('prescriptions.issued_at', '<=', CarbonImmutable::parse($criteria->issuedTo)->endOfDay());
        }

        if ($criteria->startFrom !== null) {
            $query->where('prescriptions.starts_on', '>=', $criteria->startFrom);
        }

        if ($criteria->startTo !== null) {
            $query->where('prescriptions.starts_on', '<=', $criteria->startTo);
        }

        if ($criteria->createdFrom !== null) {
            $query->where('prescriptions.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('prescriptions.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(CAST(prescriptions.id AS TEXT)) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(prescriptions.medication_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(prescriptions.medication_code, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, patients.first_name, \'\') || \' \' || patients.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.preferred_name, providers.first_name, \'\') || \' \' || providers.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(prescriptions.instructions, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(prescriptions.notes, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByRaw('COALESCE(prescriptions.issued_at, prescriptions.created_at) DESC')
            ->orderByDesc('prescriptions.created_at')
            ->orderByDesc('prescriptions.id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $prescriptionId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('prescriptions')
            ->where('tenant_id', $tenantId)
            ->where('id', $prescriptionId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $prescriptionId, array $updates): ?PrescriptionData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $prescriptionId);
        }

        unset($updates['prescription_id'], $updates['tenant_id']);

        if (array_key_exists('last_transition', $updates)) {
            $updates['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        DB::table('prescriptions')
            ->where('tenant_id', $tenantId)
            ->where('id', $prescriptionId)
            ->whereNull('deleted_at')
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $prescriptionId);
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('prescriptions')
            ->join('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'prescriptions.patient_id')
                    ->on('patients.tenant_id', '=', 'prescriptions.tenant_id');
            })
            ->join('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'prescriptions.provider_id')
                    ->on('providers.tenant_id', '=', 'prescriptions.tenant_id');
            })
            ->leftJoin('encounters', function (JoinClause $join): void {
                $join->on('encounters.id', '=', 'prescriptions.encounter_id')
                    ->on('encounters.tenant_id', '=', 'prescriptions.tenant_id');
            })
            ->leftJoin('treatment_plan_items', function (JoinClause $join): void {
                $join->on('treatment_plan_items.id', '=', 'prescriptions.treatment_item_id')
                    ->on('treatment_plan_items.tenant_id', '=', 'prescriptions.tenant_id');
            })
            ->where('prescriptions.tenant_id', $tenantId)
            ->select([
                'prescriptions.id as prescription_id',
                'prescriptions.tenant_id',
                'prescriptions.patient_id',
                'prescriptions.provider_id',
                'prescriptions.encounter_id',
                'prescriptions.treatment_item_id',
                'prescriptions.medication_name',
                'prescriptions.medication_code',
                'prescriptions.dosage',
                'prescriptions.route',
                'prescriptions.frequency',
                'prescriptions.quantity',
                'prescriptions.quantity_unit',
                'prescriptions.authorized_refills',
                'prescriptions.instructions',
                'prescriptions.notes',
                'prescriptions.starts_on',
                'prescriptions.ends_on',
                'prescriptions.status',
                'prescriptions.issued_at',
                'prescriptions.dispensed_at',
                'prescriptions.canceled_at',
                'prescriptions.cancel_reason',
                'prescriptions.last_transition',
                'prescriptions.deleted_at',
                'prescriptions.created_at',
                'prescriptions.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                DB::raw('COALESCE(encounters.summary, encounters.chief_complaint) as encounter_summary'),
                'treatment_plan_items.title as treatment_item_title',
            ]);

        if (! $withDeleted) {
            $query->whereNull('prescriptions.deleted_at');
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

    private function toData(stdClass $row): PrescriptionData
    {
        return new PrescriptionData(
            prescriptionId: $this->stringValue($row->prescription_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->displayName(
                $this->nullableString($row->patient_preferred_name ?? null),
                $this->nullableString($row->patient_first_name ?? null),
                $this->nullableString($row->patient_last_name ?? null),
                'Unknown patient',
            ),
            providerId: $this->stringValue($row->provider_id ?? null),
            providerDisplayName: $this->displayName(
                $this->nullableString($row->provider_preferred_name ?? null),
                $this->nullableString($row->provider_first_name ?? null),
                $this->nullableString($row->provider_last_name ?? null),
                'Unknown provider',
            ),
            encounterId: $this->nullableString($row->encounter_id ?? null),
            encounterSummary: $this->nullableString($row->encounter_summary ?? null),
            treatmentItemId: $this->nullableString($row->treatment_item_id ?? null),
            treatmentItemTitle: $this->nullableString($row->treatment_item_title ?? null),
            medicationName: $this->stringValue($row->medication_name ?? null),
            medicationCode: $this->nullableString($row->medication_code ?? null),
            dosage: $this->stringValue($row->dosage ?? null),
            route: $this->stringValue($row->route ?? null),
            frequency: $this->stringValue($row->frequency ?? null),
            quantity: $this->stringValue($row->quantity ?? null),
            quantityUnit: $this->nullableString($row->quantity_unit ?? null),
            authorizedRefills: is_numeric($row->authorized_refills ?? null) ? (int) $row->authorized_refills : 0,
            instructions: $this->nullableString($row->instructions ?? null),
            notes: $this->nullableString($row->notes ?? null),
            startsOn: $this->nullableString($row->starts_on ?? null),
            endsOn: $this->nullableString($row->ends_on ?? null),
            status: $this->stringValue($row->status ?? null),
            issuedAt: $this->nullableDateTime($row->issued_at ?? null),
            dispensedAt: $this->nullableDateTime($row->dispensed_at ?? null),
            canceledAt: $this->nullableDateTime($row->canceled_at ?? null),
            cancelReason: $this->nullableString($row->cancel_reason ?? null),
            lastTransition: $this->jsonArray($row->last_transition ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
