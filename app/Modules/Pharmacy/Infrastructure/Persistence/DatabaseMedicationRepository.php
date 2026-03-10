<?php

namespace App\Modules\Pharmacy\Infrastructure\Persistence;

use App\Modules\Pharmacy\Application\Contracts\MedicationRepository;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseMedicationRepository implements MedicationRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): MedicationData
    {
        $medicationId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('medications')->insert([
            'id' => $medicationId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'generic_name' => $attributes['generic_name'],
            'form' => $attributes['form'],
            'strength' => $attributes['strength'],
            'description' => $attributes['description'],
            'is_active' => $attributes['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $medicationId)
            ?? throw new \LogicException('Created medication could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $medicationId): bool
    {
        return DB::table('medications')
            ->where('tenant_id', $tenantId)
            ->where('id', $medicationId)
            ->delete() > 0;
    }

    #[\Override]
    public function findByCode(string $tenantId, string $code): ?MedicationData
    {
        $row = DB::table('medications')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $medicationId): ?MedicationData
    {
        $row = DB::table('medications')
            ->where('tenant_id', $tenantId)
            ->where('id', $medicationId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, MedicationListCriteria $criteria): array
    {
        $query = DB::table('medications')
            ->where('tenant_id', $tenantId);

        if ($criteria->isActive !== null) {
            $query->where('is_active', $criteria->isActive);
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(generic_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(form, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(strength, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('name')
            ->orderBy('code')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function codeExists(string $tenantId, string $code, ?string $ignoreMedicationId = null): bool
    {
        $query = DB::table('medications')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreMedicationId !== null) {
            $query->where('id', '!=', $ignoreMedicationId);
        }

        return $query->exists();
    }

    #[\Override]
    public function update(string $tenantId, string $medicationId, array $updates): ?MedicationData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $medicationId);
        }

        DB::table('medications')
            ->where('tenant_id', $tenantId)
            ->where('id', $medicationId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $medicationId);
    }

    private function toData(stdClass $row): MedicationData
    {
        return new MedicationData(
            medicationId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            genericName: $this->nullableString($row->generic_name ?? null),
            form: $this->nullableString($row->form ?? null),
            strength: $this->nullableString($row->strength ?? null),
            description: $this->nullableString($row->description ?? null),
            isActive: (bool) ($row->is_active ?? false),
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
