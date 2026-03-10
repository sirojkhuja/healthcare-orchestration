<?php

namespace App\Modules\Pharmacy\Infrastructure\Persistence;

use App\Modules\Pharmacy\Application\Contracts\PatientAllergyRepository;
use App\Modules\Pharmacy\Application\Data\PatientAllergyData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientAllergyRepository implements PatientAllergyRepository
{
    #[\Override]
    public function create(string $tenantId, string $patientId, array $attributes): PatientAllergyData
    {
        $allergyId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('patient_allergies')->insert([
            'id' => $allergyId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'medication_id' => $attributes['medication_id'],
            'allergen_name' => $attributes['allergen_name'],
            'reaction' => $attributes['reaction'],
            'severity' => $attributes['severity'],
            'noted_at' => $attributes['noted_at'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId, $allergyId)
            ?? throw new \LogicException('Created patient allergy could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $patientId, string $allergyId): bool
    {
        return DB::table('patient_allergies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $allergyId)
            ->delete() > 0;
    }

    #[\Override]
    public function allergenExists(
        string $tenantId,
        string $patientId,
        string $allergenName,
        ?string $ignoreAllergyId = null,
    ): bool {
        $query = DB::table('patient_allergies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->whereRaw('LOWER(allergen_name) = ?', [mb_strtolower($allergenName)]);

        if ($ignoreAllergyId !== null) {
            $query->where('id', '!=', $ignoreAllergyId);
        }

        return $query->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $allergyId): ?PatientAllergyData
    {
        $row = $this->baseQuery($tenantId, $patientId)
            ->where('patient_allergies.id', $allergyId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId, $patientId)
            ->orderBy('patient_allergies.created_at')
            ->orderBy('patient_allergies.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    private function baseQuery(string $tenantId, string $patientId): Builder
    {
        return DB::table('patient_allergies')
            ->leftJoin('medications', function (JoinClause $join): void {
                $join->on('medications.id', '=', 'patient_allergies.medication_id')
                    ->on('medications.tenant_id', '=', 'patient_allergies.tenant_id');
            })
            ->where('patient_allergies.tenant_id', $tenantId)
            ->where('patient_allergies.patient_id', $patientId)
            ->select([
                'patient_allergies.id as allergy_id',
                'patient_allergies.patient_id',
                'patient_allergies.medication_id',
                'patient_allergies.allergen_name',
                'patient_allergies.reaction',
                'patient_allergies.severity',
                'patient_allergies.noted_at',
                'patient_allergies.notes',
                'patient_allergies.created_at',
                'patient_allergies.updated_at',
                'medications.code as medication_code',
                'medications.name as medication_name',
                'medications.generic_name as medication_generic_name',
                'medications.form as medication_form',
                'medications.strength as medication_strength',
                'medications.is_active as medication_is_active',
            ]);
    }

    private function toData(stdClass $row): PatientAllergyData
    {
        return new PatientAllergyData(
            allergyId: $this->stringValue($row->allergy_id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            medicationId: $this->nullableString($row->medication_id ?? null),
            medicationCode: $this->nullableString($row->medication_code ?? null),
            medicationName: $this->nullableString($row->medication_name ?? null),
            medicationGenericName: $this->nullableString($row->medication_generic_name ?? null),
            medicationForm: $this->nullableString($row->medication_form ?? null),
            medicationStrength: $this->nullableString($row->medication_strength ?? null),
            medicationIsActive: $row->medication_is_active === null ? null : (bool) $row->medication_is_active,
            allergenName: $this->stringValue($row->allergen_name ?? null),
            reaction: $this->nullableString($row->reaction ?? null),
            severity: $this->nullableString($row->severity ?? null),
            notedAt: $this->nullableDateTime($row->noted_at ?? null),
            notes: $this->nullableString($row->notes ?? null),
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
}
