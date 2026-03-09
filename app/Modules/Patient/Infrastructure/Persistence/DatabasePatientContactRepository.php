<?php

namespace App\Modules\Patient\Infrastructure\Persistence;

use App\Modules\Patient\Application\Contracts\PatientContactRepository;
use App\Modules\Patient\Application\Data\PatientContactData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientContactRepository implements PatientContactRepository
{
    #[\Override]
    public function clearPrimaryForPatient(string $tenantId, string $patientId, ?string $ignoreContactId = null): void
    {
        $query = DB::table('patient_contacts')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('is_primary', true);

        if ($ignoreContactId !== null) {
            $query->where('id', '!=', $ignoreContactId);
        }

        $query->update([
            'is_primary' => false,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    #[\Override]
    public function create(string $tenantId, string $patientId, array $attributes): PatientContactData
    {
        $now = CarbonImmutable::now();
        $contactId = (string) Str::uuid();

        DB::table('patient_contacts')->insert([
            'id' => $contactId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'name' => $attributes['name'],
            'relationship' => $attributes['relationship'],
            'phone' => $attributes['phone'],
            'email' => $attributes['email'],
            'is_primary' => $attributes['is_primary'],
            'is_emergency' => $attributes['is_emergency'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId, $contactId)
            ?? throw new \LogicException('Created patient contact could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $patientId, string $contactId): bool
    {
        return DB::table('patient_contacts')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $contactId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $contactId): ?PatientContactData
    {
        $row = DB::table('patient_contacts')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $contactId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_contacts')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('is_primary')
            ->orderByDesc('is_emergency')
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $patientId, string $contactId, array $updates): ?PatientContactData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $patientId, $contactId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        $updated = DB::table('patient_contacts')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $contactId)
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $patientId, $contactId);
        }

        return $this->findInTenant($tenantId, $patientId, $contactId);
    }

    private function toData(stdClass $row): PatientContactData
    {
        return new PatientContactData(
            contactId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            name: $this->stringValue($row->name ?? null),
            relationship: $this->nullableString($row->relationship ?? null),
            phone: $this->nullableString($row->phone ?? null),
            email: $this->nullableString($row->email ?? null),
            isPrimary: (bool) ($row->is_primary ?? false),
            isEmergency: (bool) ($row->is_emergency ?? false),
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
