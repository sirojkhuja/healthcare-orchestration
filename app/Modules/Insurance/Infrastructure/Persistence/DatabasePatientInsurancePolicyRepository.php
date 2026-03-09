<?php

namespace App\Modules\Insurance\Infrastructure\Persistence;

use App\Modules\Insurance\Application\Contracts\PatientInsurancePolicyRepository;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientInsurancePolicyRepository implements PatientInsurancePolicyRepository
{
    #[\Override]
    public function clearPrimaryForPatient(string $tenantId, string $patientId, ?string $ignorePolicyId = null): void
    {
        $query = DB::table('patient_insurance_policies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('is_primary', true);

        if ($ignorePolicyId !== null) {
            $query->where('id', '!=', $ignorePolicyId);
        }

        $query->update([
            'is_primary' => false,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    #[\Override]
    public function create(string $tenantId, string $patientId, array $attributes): PatientInsurancePolicyData
    {
        $policyId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('patient_insurance_policies')->insert([
            'id' => $policyId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'insurance_code' => $attributes['insurance_code'],
            'policy_number' => $attributes['policy_number'],
            'member_number' => $attributes['member_number'],
            'group_number' => $attributes['group_number'],
            'plan_name' => $attributes['plan_name'],
            'effective_from' => $attributes['effective_from'],
            'effective_to' => $attributes['effective_to'],
            'is_primary' => $attributes['is_primary'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId, $policyId)
            ?? throw new \LogicException('Created patient insurance policy could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $patientId, string $policyId): bool
    {
        return DB::table('patient_insurance_policies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $policyId)
            ->delete() > 0;
    }

    #[\Override]
    public function existsDuplicate(string $tenantId, string $patientId, string $insuranceCode, string $policyNumber): bool
    {
        return DB::table('patient_insurance_policies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('insurance_code', $insuranceCode)
            ->where('policy_number', $policyNumber)
            ->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $policyId): ?PatientInsurancePolicyData
    {
        $row = DB::table('patient_insurance_policies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $policyId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_insurance_policies')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('is_primary')
            ->orderByRaw('effective_from desc nulls last')
            ->orderByDesc('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    private function toData(stdClass $row): PatientInsurancePolicyData
    {
        return new PatientInsurancePolicyData(
            policyId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            insuranceCode: $this->stringValue($row->insurance_code ?? null),
            policyNumber: $this->stringValue($row->policy_number ?? null),
            memberNumber: $this->nullableString($row->member_number ?? null),
            groupNumber: $this->nullableString($row->group_number ?? null),
            planName: $this->nullableString($row->plan_name ?? null),
            effectiveFrom: $this->nullableDateTime($row->effective_from ?? null),
            effectiveTo: $this->nullableDateTime($row->effective_to ?? null),
            isPrimary: (bool) ($row->is_primary ?? false),
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
