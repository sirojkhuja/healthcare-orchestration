<?php

namespace App\Modules\Treatment\Infrastructure\Persistence;

use App\Modules\Treatment\Application\Contracts\TreatmentPlanRepository;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Data\TreatmentPlanSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseTreatmentPlanRepository implements TreatmentPlanRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): TreatmentPlanData
    {
        $planId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('treatment_plans')->insert([
            'id' => $planId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'title' => $attributes['title'],
            'summary' => $attributes['summary'],
            'goals' => $attributes['goals'],
            'planned_start_date' => $attributes['planned_start_date'],
            'planned_end_date' => $attributes['planned_end_date'],
            'status' => $attributes['status'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'approved_at' => $attributes['approved_at'],
            'started_at' => $attributes['started_at'],
            'paused_at' => $attributes['paused_at'],
            'finished_at' => $attributes['finished_at'],
            'rejected_at' => $attributes['rejected_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $planId)
            ?? throw new \LogicException('Created treatment plan could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $planId, bool $withDeleted = false): ?TreatmentPlanData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('treatment_plans.id', $planId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId)
            ->orderByDesc('treatment_plans.created_at')
            ->orderBy('treatment_plans.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function search(string $tenantId, TreatmentPlanSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);

        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
            'provider_id' => $criteria->providerId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('treatment_plans.'.$column, $value);
            }
        }

        if ($criteria->plannedFrom !== null) {
            $query->where('treatment_plans.planned_start_date', '>=', $criteria->plannedFrom);
        }

        if ($criteria->plannedTo !== null) {
            $query->where('treatment_plans.planned_end_date', '<=', $criteria->plannedTo);
        }

        if ($criteria->createdFrom !== null) {
            $query->where('treatment_plans.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('treatment_plans.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null) {
            $pattern = '%'.mb_strtolower($criteria->query).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(treatment_plans.id::text) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(treatment_plans.title) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, patients.first_name, \'\') || \' \' || patients.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.preferred_name, providers.first_name, \'\') || \' \' || providers.last_name) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('treatment_plans.created_at')
            ->orderBy('treatment_plans.id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $planId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('treatment_plans')
            ->where('tenant_id', $tenantId)
            ->where('id', $planId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $planId, array $updates): ?TreatmentPlanData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $planId);
        }

        /** @var array<string, mixed> $payload */
        $payload = array_replace(['updated_at' => CarbonImmutable::now()], $updates);

        if (array_key_exists('last_transition', $updates)) {
            $payload['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        $updated = DB::table('treatment_plans')
            ->where('tenant_id', $tenantId)
            ->where('id', $planId)
            ->whereNull('deleted_at')
            ->update($payload);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $planId);
        }

        return $this->findInTenant($tenantId, $planId);
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('treatment_plans')
            ->join('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'treatment_plans.patient_id')
                    ->on('patients.tenant_id', '=', 'treatment_plans.tenant_id');
            })
            ->join('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'treatment_plans.provider_id')
                    ->on('providers.tenant_id', '=', 'treatment_plans.tenant_id');
            })
            ->where('treatment_plans.tenant_id', $tenantId)
            ->select([
                'treatment_plans.id',
                'treatment_plans.tenant_id',
                'treatment_plans.patient_id',
                'treatment_plans.provider_id',
                'treatment_plans.title',
                'treatment_plans.summary',
                'treatment_plans.goals',
                'treatment_plans.planned_start_date',
                'treatment_plans.planned_end_date',
                'treatment_plans.status',
                'treatment_plans.last_transition',
                'treatment_plans.approved_at',
                'treatment_plans.started_at',
                'treatment_plans.paused_at',
                'treatment_plans.finished_at',
                'treatment_plans.rejected_at',
                'treatment_plans.deleted_at',
                'treatment_plans.created_at',
                'treatment_plans.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
            ]);

        if (! $withDeleted) {
            $query->whereNull('treatment_plans.deleted_at');
        }

        return $query;
    }

    private function displayName(?string $preferredName, ?string $firstName, ?string $lastName, string $fallback): string
    {
        $parts = array_values(array_filter([
            $preferredName ?? $firstName,
            $lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallback;
    }

    private function jsonValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        /** @var array<string, mixed> $value */

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastTransition(mixed $value): ?array
    {
        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
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

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(stdClass $row): TreatmentPlanData
    {
        return new TreatmentPlanData(
            planId: $this->stringValue($row->id ?? null),
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
            title: $this->stringValue($row->title ?? null),
            summary: $this->nullableString($row->summary ?? null),
            goals: $this->nullableString($row->goals ?? null),
            plannedStartDate: $this->nullableString($row->planned_start_date ?? null),
            plannedEndDate: $this->nullableString($row->planned_end_date ?? null),
            status: $this->stringValue($row->status ?? null),
            lastTransition: $this->lastTransition($row->last_transition ?? null),
            approvedAt: $this->nullableDateTime($row->approved_at ?? null),
            startedAt: $this->nullableDateTime($row->started_at ?? null),
            pausedAt: $this->nullableDateTime($row->paused_at ?? null),
            finishedAt: $this->nullableDateTime($row->finished_at ?? null),
            rejectedAt: $this->nullableDateTime($row->rejected_at ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
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

        return CarbonImmutable::parse($this->stringValue($value));
    }
}
