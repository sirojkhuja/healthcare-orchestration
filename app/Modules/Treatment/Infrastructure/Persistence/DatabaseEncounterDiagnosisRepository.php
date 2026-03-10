<?php

namespace App\Modules\Treatment\Infrastructure\Persistence;

use App\Modules\Treatment\Application\Contracts\EncounterDiagnosisRepository;
use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;
use App\Modules\Treatment\Domain\Encounters\DiagnosisType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEncounterDiagnosisRepository implements EncounterDiagnosisRepository
{
    #[\Override]
    public function create(string $tenantId, string $encounterId, array $attributes): EncounterDiagnosisData
    {
        $diagnosisId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('encounter_diagnoses')->insert([
            'id' => $diagnosisId,
            'tenant_id' => $tenantId,
            'encounter_id' => $encounterId,
            'code' => $attributes['code'],
            'display_name' => $attributes['display_name'],
            'diagnosis_type' => $attributes['diagnosis_type'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInEncounter($tenantId, $encounterId, $diagnosisId)
            ?? throw new \LogicException('Created encounter diagnosis could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $encounterId, string $diagnosisId): bool
    {
        return DB::table('encounter_diagnoses')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->where('id', $diagnosisId)
            ->delete() > 0;
    }

    #[\Override]
    public function duplicateExists(
        string $tenantId,
        string $encounterId,
        ?string $code,
        string $displayName,
        string $diagnosisType,
    ): bool {
        return DB::table('encounter_diagnoses')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->whereRaw('LOWER(COALESCE(code, \'\')) = ?', [mb_strtolower($code ?? '')])
            ->whereRaw('LOWER(display_name) = ?', [mb_strtolower($displayName)])
            ->where('diagnosis_type', $diagnosisType)
            ->exists();
    }

    #[\Override]
    public function findInEncounter(string $tenantId, string $encounterId, string $diagnosisId): ?EncounterDiagnosisData
    {
        $row = DB::table('encounter_diagnoses')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->where('id', $diagnosisId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForEncounter(string $tenantId, string $encounterId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('encounter_diagnoses')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->orderByRaw('CASE WHEN diagnosis_type = ? THEN 0 ELSE 1 END', [DiagnosisType::PRIMARY->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function primaryDiagnosisExists(string $tenantId, string $encounterId): bool
    {
        return DB::table('encounter_diagnoses')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->where('diagnosis_type', DiagnosisType::PRIMARY->value)
            ->exists();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(stdClass $row): EncounterDiagnosisData
    {
        return new EncounterDiagnosisData(
            diagnosisId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            encounterId: $this->stringValue($row->encounter_id ?? null),
            code: $this->nullableString($row->code ?? null),
            displayName: $this->stringValue($row->display_name ?? null),
            diagnosisType: $this->stringValue($row->diagnosis_type ?? null),
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
}
