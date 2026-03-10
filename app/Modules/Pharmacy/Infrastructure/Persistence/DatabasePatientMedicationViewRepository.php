<?php

namespace App\Modules\Pharmacy\Infrastructure\Persistence;

use App\Modules\Pharmacy\Application\Contracts\PatientMedicationViewRepository;
use App\Modules\Pharmacy\Application\Data\PatientMedicationData;
use App\Modules\Pharmacy\Application\Data\PatientMedicationListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DatabasePatientMedicationViewRepository implements PatientMedicationViewRepository
{
    #[\Override]
    public function listForPatient(
        string $tenantId,
        string $patientId,
        PatientMedicationListCriteria $criteria,
    ): array {
        $query = DB::table('prescriptions')
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
            ->leftJoin('medications', function (JoinClause $join): void {
                $join->on('medications.tenant_id', '=', 'prescriptions.tenant_id');
                $join->whereRaw("medications.code = UPPER(COALESCE(prescriptions.medication_code, ''))");
            })
            ->where('prescriptions.tenant_id', $tenantId)
            ->where('prescriptions.patient_id', $patientId)
            ->whereNull('prescriptions.deleted_at')
            ->whereIn('prescriptions.status', ['issued', 'dispensed', 'canceled'])
            ->select([
                'prescriptions.id as prescription_id',
                'prescriptions.status',
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
                'prescriptions.issued_at',
                'prescriptions.dispensed_at',
                'prescriptions.canceled_at',
                'prescriptions.cancel_reason',
                'prescriptions.created_at',
                'prescriptions.updated_at',
                'providers.id as provider_id',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                'encounters.id as encounter_id',
                DB::raw('COALESCE(encounters.summary, encounters.chief_complaint) as encounter_summary'),
                'treatment_plan_items.id as treatment_item_id',
                'treatment_plan_items.title as treatment_item_title',
                'medications.id as catalog_medication_id',
                'medications.code as catalog_code',
                'medications.name as catalog_name',
                'medications.generic_name as catalog_generic_name',
                'medications.form as catalog_form',
                'medications.strength as catalog_strength',
                'medications.is_active as catalog_is_active',
            ]);

        if ($criteria->status !== null) {
            $query->where('prescriptions.status', $criteria->status);
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

    private function displayName(?string $preferredName, ?string $firstName, ?string $lastName, string $fallback): string
    {
        $parts = array_values(array_filter([
            $preferredName ?? $firstName,
            $lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallback;
    }

    private function toData(stdClass $row): PatientMedicationData
    {
        return new PatientMedicationData(
            prescriptionId: $this->stringValue($row->prescription_id ?? null),
            status: $this->stringValue($row->status ?? null),
            medicationName: $this->stringValue($row->medication_name ?? null),
            medicationCode: $this->nullableString($row->medication_code ?? null),
            catalogMedicationId: $this->nullableString($row->catalog_medication_id ?? null),
            catalogCode: $this->nullableString($row->catalog_code ?? null),
            catalogName: $this->nullableString($row->catalog_name ?? null),
            catalogGenericName: $this->nullableString($row->catalog_generic_name ?? null),
            catalogForm: $this->nullableString($row->catalog_form ?? null),
            catalogStrength: $this->nullableString($row->catalog_strength ?? null),
            catalogIsActive: $row->catalog_is_active === null ? null : (bool) $row->catalog_is_active,
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
            issuedAt: $this->nullableDateTime($row->issued_at ?? null),
            dispensedAt: $this->nullableDateTime($row->dispensed_at ?? null),
            canceledAt: $this->nullableDateTime($row->canceled_at ?? null),
            cancelReason: $this->nullableString($row->cancel_reason ?? null),
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
