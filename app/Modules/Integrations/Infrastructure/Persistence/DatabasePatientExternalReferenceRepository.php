<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\PatientExternalReferenceRepository;
use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientExternalReferenceRepository implements PatientExternalReferenceRepository
{
    #[\Override]
    public function create(string $tenantId, string $patientId, array $attributes): PatientExternalReferenceData
    {
        $referenceId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('patient_external_references')->insert([
            'id' => $referenceId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'integration_key' => $attributes['integration_key'],
            'external_id' => $attributes['external_id'],
            'external_type' => $attributes['external_type'],
            'display_name' => $attributes['display_name'],
            'metadata' => json_encode($attributes['metadata'], JSON_THROW_ON_ERROR),
            'linked_at' => $attributes['linked_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId, $referenceId)
            ?? throw new \LogicException('Created patient external reference could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $patientId, string $referenceId): bool
    {
        return DB::table('patient_external_references')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $referenceId)
            ->delete() > 0;
    }

    #[\Override]
    public function existsDuplicate(
        string $tenantId,
        string $patientId,
        string $integrationKey,
        string $externalType,
        string $externalId,
    ): bool {
        return DB::table('patient_external_references')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('integration_key', $integrationKey)
            ->where('external_type', $externalType)
            ->where('external_id', $externalId)
            ->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $referenceId): ?PatientExternalReferenceData
    {
        $row = DB::table('patient_external_references')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $referenceId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_external_references')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderBy('integration_key')
            ->orderBy('external_type')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    private function toData(stdClass $row): PatientExternalReferenceData
    {
        return new PatientExternalReferenceData(
            referenceId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            externalId: $this->stringValue($row->external_id ?? null),
            externalType: $this->stringValue($row->external_type ?? null),
            displayName: $this->nullableString($row->display_name ?? null),
            metadata: $this->arrayValue($row->metadata ?? null),
            linkedAt: $this->dateTime($row->linked_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
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
