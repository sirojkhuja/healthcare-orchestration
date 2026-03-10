<?php

namespace App\Modules\Treatment\Infrastructure\Persistence;

use App\Modules\Treatment\Application\Contracts\EncounterProcedureRepository;
use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEncounterProcedureRepository implements EncounterProcedureRepository
{
    #[\Override]
    public function create(string $tenantId, string $encounterId, array $attributes): EncounterProcedureData
    {
        $procedureId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('encounter_procedures')->insert([
            'id' => $procedureId,
            'tenant_id' => $tenantId,
            'encounter_id' => $encounterId,
            'treatment_item_id' => $attributes['treatment_item_id'],
            'code' => $attributes['code'],
            'display_name' => $attributes['display_name'],
            'performed_at' => $attributes['performed_at'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInEncounter($tenantId, $encounterId, $procedureId)
            ?? throw new \LogicException('Created encounter procedure could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $encounterId, string $procedureId): bool
    {
        return DB::table('encounter_procedures')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->where('id', $procedureId)
            ->delete() > 0;
    }

    #[\Override]
    public function duplicateExists(
        string $tenantId,
        string $encounterId,
        ?string $code,
        string $displayName,
        ?CarbonImmutable $performedAt,
        ?string $treatmentItemId,
    ): bool {
        $query = DB::table('encounter_procedures')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->whereRaw('LOWER(COALESCE(code, \'\')) = ?', [mb_strtolower($code ?? '')])
            ->whereRaw('LOWER(display_name) = ?', [mb_strtolower($displayName)]);

        if ($performedAt === null) {
            $query->whereNull('performed_at');
        } else {
            $query->where('performed_at', $performedAt);
        }

        if ($treatmentItemId === null) {
            $query->whereNull('treatment_item_id');
        } else {
            $query->where('treatment_item_id', $treatmentItemId);
        }

        return $query->exists();
    }

    #[\Override]
    public function findInEncounter(string $tenantId, string $encounterId, string $procedureId): ?EncounterProcedureData
    {
        $row = DB::table('encounter_procedures')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->where('id', $procedureId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForEncounter(string $tenantId, string $encounterId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('encounter_procedures')
            ->where('tenant_id', $tenantId)
            ->where('encounter_id', $encounterId)
            ->orderByRaw('CASE WHEN performed_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('performed_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
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

    private function toData(stdClass $row): EncounterProcedureData
    {
        return new EncounterProcedureData(
            procedureId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            encounterId: $this->stringValue($row->encounter_id ?? null),
            treatmentItemId: $this->nullableString($row->treatment_item_id ?? null),
            code: $this->nullableString($row->code ?? null),
            displayName: $this->stringValue($row->display_name ?? null),
            performedAt: $this->nullableDateTime($row->performed_at ?? null),
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
