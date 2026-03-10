<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\BillableServiceRepository;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Data\BillableServiceListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseBillableServiceRepository implements BillableServiceRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): BillableServiceData
    {
        $serviceId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('billable_services')->insert([
            'id' => $serviceId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'category' => $attributes['category'],
            'unit' => $attributes['unit'],
            'description' => $attributes['description'],
            'is_active' => $attributes['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $serviceId)
            ?? throw new \LogicException('Created billable service could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $serviceId): bool
    {
        return DB::table('billable_services')
            ->where('tenant_id', $tenantId)
            ->where('id', $serviceId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $serviceId): ?BillableServiceData
    {
        $row = DB::table('billable_services')
            ->where('tenant_id', $tenantId)
            ->where('id', $serviceId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listByIds(string $tenantId, array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        /** @var list<stdClass> $rows */
        $rows = DB::table('billable_services')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $serviceIds)
            ->orderBy('name')
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listForTenant(string $tenantId, BillableServiceListCriteria $criteria): array
    {
        $query = DB::table('billable_services')
            ->where('tenant_id', $tenantId);

        if ($criteria->category !== null && trim($criteria->category) !== '') {
            $query->whereRaw('LOWER(COALESCE(category, \'\')) = ?', [mb_strtolower(trim($criteria->category))]);
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
                    ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(unit, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$pattern]);
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
    public function codeExists(string $tenantId, string $code, ?string $ignoreServiceId = null): bool
    {
        $query = DB::table('billable_services')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreServiceId !== null) {
            $query->where('id', '!=', $ignoreServiceId);
        }

        return $query->exists();
    }

    #[\Override]
    public function isReferencedInPriceLists(string $tenantId, string $serviceId): bool
    {
        return DB::table('price_list_items')
            ->where('tenant_id', $tenantId)
            ->where('service_id', $serviceId)
            ->exists();
    }

    #[\Override]
    public function update(string $tenantId, string $serviceId, array $updates): ?BillableServiceData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $serviceId);
        }

        DB::table('billable_services')
            ->where('tenant_id', $tenantId)
            ->where('id', $serviceId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $serviceId);
    }

    private function toData(stdClass $row): BillableServiceData
    {
        return new BillableServiceData(
            serviceId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            category: $this->nullableString($row->category ?? null),
            unit: $this->nullableString($row->unit ?? null),
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
