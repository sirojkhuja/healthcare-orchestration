<?php

namespace App\Modules\Treatment\Infrastructure\Persistence;

use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Data\EncounterListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEncounterRepository implements EncounterRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): EncounterData
    {
        $encounterId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('encounters')->insert([
            'id' => $encounterId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'treatment_plan_id' => $attributes['treatment_plan_id'],
            'appointment_id' => $attributes['appointment_id'],
            'clinic_id' => $attributes['clinic_id'],
            'room_id' => $attributes['room_id'],
            'status' => $attributes['status'],
            'encountered_at' => $attributes['encountered_at'],
            'timezone' => $attributes['timezone'],
            'chief_complaint' => $attributes['chief_complaint'],
            'summary' => $attributes['summary'],
            'notes' => $attributes['notes'],
            'follow_up_instructions' => $attributes['follow_up_instructions'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $encounterId)
            ?? throw new \LogicException('Created encounter could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $encounterId, bool $withDeleted = false): ?EncounterData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('encounters.id', $encounterId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, EncounterListCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);

        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
            'provider_id' => $criteria->providerId,
            'treatment_plan_id' => $criteria->treatmentPlanId,
            'appointment_id' => $criteria->appointmentId,
            'clinic_id' => $criteria->clinicId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('encounters.'.$column, $value);
            }
        }

        if ($criteria->encounterFrom !== null) {
            $query->where('encounters.encountered_at', '>=', CarbonImmutable::parse($criteria->encounterFrom));
        }

        if ($criteria->encounterTo !== null) {
            $query->where('encounters.encountered_at', '<=', CarbonImmutable::parse($criteria->encounterTo));
        }

        if ($criteria->createdFrom !== null) {
            $query->where('encounters.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('encounters.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null) {
            $pattern = '%'.mb_strtolower($criteria->query).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(CAST(encounters.id AS TEXT)) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, patients.first_name, \'\') || \' \' || patients.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.preferred_name, providers.first_name, \'\') || \' \' || providers.last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(encounters.chief_complaint, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(encounters.summary, \'\')) LIKE ?', [$pattern])
                    ->orWhereExists(function (Builder $diagnosisQuery) use ($pattern): void {
                        $diagnosisQuery->selectRaw('1')
                            ->from('encounter_diagnoses')
                            ->whereColumn('encounter_diagnoses.encounter_id', 'encounters.id')
                            ->whereColumn('encounter_diagnoses.tenant_id', 'encounters.tenant_id')
                            ->where(function (Builder $inner) use ($pattern): void {
                                $inner
                                    ->whereRaw('LOWER(encounter_diagnoses.display_name) LIKE ?', [$pattern])
                                    ->orWhereRaw('LOWER(COALESCE(encounter_diagnoses.code, \'\')) LIKE ?', [$pattern]);
                            });
                    })
                    ->orWhereExists(function (Builder $procedureQuery) use ($pattern): void {
                        $procedureQuery->selectRaw('1')
                            ->from('encounter_procedures')
                            ->whereColumn('encounter_procedures.encounter_id', 'encounters.id')
                            ->whereColumn('encounter_procedures.tenant_id', 'encounters.tenant_id')
                            ->where(function (Builder $inner) use ($pattern): void {
                                $inner
                                    ->whereRaw('LOWER(encounter_procedures.display_name) LIKE ?', [$pattern])
                                    ->orWhereRaw('LOWER(COALESCE(encounter_procedures.code, \'\')) LIKE ?', [$pattern]);
                            });
                    });
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('encounters.encountered_at')
            ->orderByDesc('encounters.created_at')
            ->orderByDesc('encounters.id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $encounterId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('encounters')
            ->where('tenant_id', $tenantId)
            ->where('id', $encounterId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $encounterId, array $updates): ?EncounterData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $encounterId);
        }

        $payload = array_replace(['updated_at' => CarbonImmutable::now()], $updates);
        $updated = DB::table('encounters')
            ->where('tenant_id', $tenantId)
            ->where('id', $encounterId)
            ->whereNull('deleted_at')
            ->update($payload);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $encounterId);
        }

        return $this->findInTenant($tenantId, $encounterId);
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('encounters')
            ->join('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'encounters.patient_id')
                    ->on('patients.tenant_id', '=', 'encounters.tenant_id');
            })
            ->join('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'encounters.provider_id')
                    ->on('providers.tenant_id', '=', 'encounters.tenant_id');
            })
            ->leftJoin('clinics', function (JoinClause $join): void {
                $join->on('clinics.id', '=', 'encounters.clinic_id')
                    ->on('clinics.tenant_id', '=', 'encounters.tenant_id');
            })
            ->leftJoin('clinic_rooms', function (JoinClause $join): void {
                $join->on('clinic_rooms.id', '=', 'encounters.room_id')
                    ->on('clinic_rooms.tenant_id', '=', 'encounters.tenant_id');
            })
            ->where('encounters.tenant_id', $tenantId)
            ->select([
                'encounters.id',
                'encounters.tenant_id',
                'encounters.patient_id',
                'encounters.provider_id',
                'encounters.treatment_plan_id',
                'encounters.appointment_id',
                'encounters.clinic_id',
                'encounters.room_id',
                'encounters.status',
                'encounters.encountered_at',
                'encounters.timezone',
                'encounters.chief_complaint',
                'encounters.summary',
                'encounters.notes',
                'encounters.follow_up_instructions',
                DB::raw('(select count(*) from encounter_diagnoses where encounter_diagnoses.encounter_id = encounters.id and encounter_diagnoses.tenant_id = encounters.tenant_id) as diagnosis_count'),
                DB::raw('(select count(*) from encounter_procedures where encounter_procedures.encounter_id = encounters.id and encounter_procedures.tenant_id = encounters.tenant_id) as procedure_count'),
                'encounters.deleted_at',
                'encounters.created_at',
                'encounters.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                'clinics.name as clinic_name',
                'clinic_rooms.name as room_name',
            ]);

        if (! $withDeleted) {
            $query->whereNull('encounters.deleted_at');
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

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toData(stdClass $row): EncounterData
    {
        return new EncounterData(
            encounterId: $this->stringValue($row->id ?? null),
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
            treatmentPlanId: $this->nullableString($row->treatment_plan_id ?? null),
            appointmentId: $this->nullableString($row->appointment_id ?? null),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            clinicName: $this->nullableString($row->clinic_name ?? null),
            roomId: $this->nullableString($row->room_id ?? null),
            roomName: $this->nullableString($row->room_name ?? null),
            status: $this->stringValue($row->status ?? null),
            encounteredAt: $this->dateTime($row->encountered_at ?? null),
            timezone: $this->stringValue($row->timezone ?? null),
            chiefComplaint: $this->nullableString($row->chief_complaint ?? null),
            summary: $this->nullableString($row->summary ?? null),
            notes: $this->nullableString($row->notes ?? null),
            followUpInstructions: $this->nullableString($row->follow_up_instructions ?? null),
            diagnosisCount: $this->intValue($row->diagnosis_count ?? null),
            procedureCount: $this->intValue($row->procedure_count ?? null),
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
