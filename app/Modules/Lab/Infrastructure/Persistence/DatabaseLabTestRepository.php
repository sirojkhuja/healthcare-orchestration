<?php

namespace App\Modules\Lab\Infrastructure\Persistence;

use App\Modules\Lab\Application\Contracts\LabTestRepository;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Data\LabTestListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseLabTestRepository implements LabTestRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): LabTestData
    {
        $testId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('lab_tests')->insert([
            'id' => $testId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'specimen_type' => $attributes['specimen_type'],
            'result_type' => $attributes['result_type'],
            'unit' => $attributes['unit'],
            'reference_range' => $attributes['reference_range'],
            'lab_provider_key' => $attributes['lab_provider_key'],
            'external_test_code' => $attributes['external_test_code'],
            'is_active' => $attributes['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $testId)
            ?? throw new \LogicException('Created lab test could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $testId): bool
    {
        return DB::table('lab_tests')
            ->where('tenant_id', $tenantId)
            ->where('id', $testId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $testId): ?LabTestData
    {
        $row = DB::table('lab_tests')
            ->where('tenant_id', $tenantId)
            ->where('id', $testId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, LabTestListCriteria $criteria): array
    {
        $query = DB::table('lab_tests')
            ->where('tenant_id', $tenantId);

        if ($criteria->labProviderKey !== null) {
            $query->where('lab_provider_key', $criteria->labProviderKey);
        }

        if ($criteria->isActive !== null) {
            $query->where('is_active', $criteria->isActive);
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(external_test_code, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('name')
            ->orderBy('code')
            ->orderBy('created_at')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function providerCodeExists(
        string $tenantId,
        string $labProviderKey,
        string $code,
        ?string $ignoreTestId = null,
    ): bool {
        $query = DB::table('lab_tests')
            ->where('tenant_id', $tenantId)
            ->where('lab_provider_key', $labProviderKey)
            ->where('code', $code);

        if ($ignoreTestId !== null) {
            $query->where('id', '!=', $ignoreTestId);
        }

        return $query->exists();
    }

    #[\Override]
    public function update(string $tenantId, string $testId, array $updates): ?LabTestData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $testId);
        }

        DB::table('lab_tests')
            ->where('tenant_id', $tenantId)
            ->where('id', $testId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $testId);
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
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

    private function toData(stdClass $row): LabTestData
    {
        return new LabTestData(
            testId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            specimenType: $this->stringValue($row->specimen_type ?? null),
            resultType: $this->stringValue($row->result_type ?? null),
            unit: $this->nullableString($row->unit ?? null),
            referenceRange: $this->nullableString($row->reference_range ?? null),
            labProviderKey: $this->stringValue($row->lab_provider_key ?? null),
            externalTestCode: $this->nullableString($row->external_test_code ?? null),
            isActive: $this->boolValue($row->is_active ?? false),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
