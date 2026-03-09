<?php

namespace App\Modules\Patient\Infrastructure\Persistence;

use App\Modules\Patient\Application\Contracts\PatientConsentRepository;
use App\Modules\Patient\Application\Data\PatientConsentData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientConsentRepository implements PatientConsentRepository
{
    #[\Override]
    public function create(string $tenantId, string $patientId, array $attributes): PatientConsentData
    {
        $consentId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('patient_consents')->insert([
            'id' => $consentId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'consent_type' => $attributes['consent_type'],
            'granted_by_name' => $attributes['granted_by_name'],
            'granted_by_relationship' => $attributes['granted_by_relationship'],
            'granted_at' => $attributes['granted_at'],
            'expires_at' => $attributes['expires_at'],
            'revoked_at' => null,
            'revocation_reason' => null,
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId, $consentId)
            ?? throw new \LogicException('Created patient consent could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $consentId): ?PatientConsentData
    {
        $row = DB::table('patient_consents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $consentId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function hasActiveConsentType(
        string $tenantId,
        string $patientId,
        string $consentType,
        CarbonImmutable $now,
    ): bool {
        return DB::table('patient_consents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('consent_type', $consentType)
            ->whereNull('revoked_at')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_consents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('granted_at')
            ->orderByDesc('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function revoke(
        string $tenantId,
        string $patientId,
        string $consentId,
        CarbonImmutable $revokedAt,
        ?string $reason,
    ): ?PatientConsentData {
        $updated = DB::table('patient_consents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $consentId)
            ->update([
                'revoked_at' => $revokedAt,
                'revocation_reason' => $reason,
                'updated_at' => $revokedAt,
            ]);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $patientId, $consentId);
        }

        return $this->findInTenant($tenantId, $patientId, $consentId);
    }

    private function toData(stdClass $row): PatientConsentData
    {
        return new PatientConsentData(
            consentId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            consentType: $this->stringValue($row->consent_type ?? null),
            grantedByName: $this->stringValue($row->granted_by_name ?? null),
            grantedByRelationship: $this->nullableString($row->granted_by_relationship ?? null),
            grantedAt: $this->dateTime($row->granted_at ?? null),
            expiresAt: $this->nullableDateTime($row->expires_at ?? null),
            revokedAt: $this->nullableDateTime($row->revoked_at ?? null),
            revocationReason: $this->nullableString($row->revocation_reason ?? null),
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
