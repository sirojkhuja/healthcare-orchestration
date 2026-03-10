<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\PriceListRepository;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Data\PriceListItemData;
use App\Modules\Billing\Application\Data\PriceListListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePriceListRepository implements PriceListRepository
{
    #[\Override]
    public function clearDefaultFlags(string $tenantId, ?string $ignorePriceListId = null): void
    {
        $query = DB::table('price_lists')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true);

        if ($ignorePriceListId !== null) {
            $query->where('id', '!=', $ignorePriceListId);
        }

        $query->update([
            'is_default' => false,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    #[\Override]
    public function create(string $tenantId, array $attributes): PriceListData
    {
        $priceListId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('price_lists')->insert([
            'id' => $priceListId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'currency' => $attributes['currency'],
            'is_default' => $attributes['is_default'],
            'is_active' => $attributes['is_active'],
            'effective_from' => $attributes['effective_from'],
            'effective_to' => $attributes['effective_to'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $priceListId)
            ?? throw new \LogicException('Created price list could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $priceListId): bool
    {
        return DB::table('price_lists')
            ->where('tenant_id', $tenantId)
            ->where('id', $priceListId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $priceListId): ?PriceListData
    {
        $row = $this->priceListRowsQuery($tenantId)
            ->where('pl.id', $priceListId)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        $items = $this->loadItems($tenantId, $priceListId, $this->stringValue($row->currency ?? null));

        return $this->toData($row, $items);
    }

    #[\Override]
    public function listDefaultsForTenant(string $tenantId, ?string $ignorePriceListId = null): array
    {
        $query = $this->priceListRowsQuery($tenantId)
            ->where('pl.is_default', true);

        if ($ignorePriceListId !== null) {
            $query->where('pl.id', '!=', $ignorePriceListId);
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('pl.name')
            ->orderBy('pl.code')
            ->orderBy('pl.id')
            ->get()
            ->all();

        return array_map(fn (stdClass $row): PriceListData => $this->toData($row), $rows);
    }

    #[\Override]
    public function listForTenant(string $tenantId, PriceListListCriteria $criteria): array
    {
        $query = $this->priceListRowsQuery($tenantId);

        if ($criteria->isActive !== null) {
            $query->where('pl.is_active', $criteria->isActive);
        }

        if ($criteria->isDefault !== null) {
            $query->where('pl.is_default', $criteria->isDefault);
        }

        if ($criteria->activeOn instanceof CarbonImmutable) {
            $date = $criteria->activeOn->toDateString();
            $query
                ->where('pl.is_active', true)
                ->where(function (Builder $builder) use ($date): void {
                    $builder
                        ->whereNull('pl.effective_from')
                        ->orWhere('pl.effective_from', '<=', $date);
                })
                ->where(function (Builder $builder) use ($date): void {
                    $builder
                        ->whereNull('pl.effective_to')
                        ->orWhere('pl.effective_to', '>=', $date);
                });
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(pl.code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(pl.name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(pl.description, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(pl.currency) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('pl.is_default')
            ->orderByDesc('pl.is_active')
            ->orderBy('pl.name')
            ->orderBy('pl.code')
            ->orderBy('pl.created_at')
            ->orderBy('pl.id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(fn (stdClass $row): PriceListData => $this->toData($row), $rows);
    }

    #[\Override]
    public function codeExists(string $tenantId, string $code, ?string $ignorePriceListId = null): bool
    {
        $query = DB::table('price_lists')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignorePriceListId !== null) {
            $query->where('id', '!=', $ignorePriceListId);
        }

        return $query->exists();
    }

    #[\Override]
    public function replaceItems(string $tenantId, string $priceListId, array $items): void
    {
        DB::transaction(function () use ($tenantId, $priceListId, $items): void {
            DB::table('price_list_items')
                ->where('tenant_id', $tenantId)
                ->where('price_list_id', $priceListId)
                ->delete();

            if ($items === []) {
                return;
            }

            $now = CarbonImmutable::now();

            DB::table('price_list_items')->insert(array_map(
                fn (array $item): array => [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'price_list_id' => $priceListId,
                    'service_id' => $item['service_id'],
                    'unit_price_amount' => $item['amount'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $items,
            ));
        });
    }

    #[\Override]
    public function update(string $tenantId, string $priceListId, array $updates): ?PriceListData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $priceListId);
        }

        DB::table('price_lists')
            ->where('tenant_id', $tenantId)
            ->where('id', $priceListId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $priceListId);
    }

    /**
     * @return list<PriceListItemData>
     */
    private function loadItems(string $tenantId, string $priceListId, string $currency): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('price_list_items as pli')
            ->join('billable_services as bs', function (JoinClause $join): void {
                $join
                    ->on('bs.id', '=', 'pli.service_id')
                    ->on('bs.tenant_id', '=', 'pli.tenant_id');
            })
            ->where('pli.tenant_id', $tenantId)
            ->where('pli.price_list_id', $priceListId)
            ->select([
                'pli.id',
                'pli.price_list_id',
                'pli.tenant_id',
                'pli.service_id',
                'pli.unit_price_amount',
                'pli.created_at',
                'pli.updated_at',
                'bs.code as service_code',
                'bs.name as service_name',
                'bs.category as service_category',
                'bs.unit as service_unit',
                'bs.is_active as service_is_active',
            ])
            ->orderBy('bs.name')
            ->orderBy('bs.code')
            ->orderBy('pli.id')
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): PriceListItemData => $this->toItemData($row, $currency),
            $rows,
        );
    }

    private function priceListRowsQuery(string $tenantId): Builder
    {
        $itemCounts = DB::table('price_list_items')
            ->selectRaw('price_list_id, COUNT(*) as item_count')
            ->where('tenant_id', $tenantId)
            ->groupBy('price_list_id');

        return DB::table('price_lists as pl')
            ->leftJoinSub($itemCounts, 'item_counts', function (JoinClause $join): void {
                $join->on('item_counts.price_list_id', '=', 'pl.id');
            })
            ->where('pl.tenant_id', $tenantId)
            ->select([
                'pl.id',
                'pl.tenant_id',
                'pl.code',
                'pl.name',
                'pl.description',
                'pl.currency',
                'pl.is_default',
                'pl.is_active',
                'pl.effective_from',
                'pl.effective_to',
                'pl.created_at',
                'pl.updated_at',
                DB::raw('COALESCE(item_counts.item_count, 0) as item_count'),
            ]);
    }

    /**
     * @param  list<PriceListItemData>  $items
     */
    private function toData(stdClass $row, array $items = []): PriceListData
    {
        return new PriceListData(
            priceListId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            currency: $this->stringValue($row->currency ?? null),
            isDefault: (bool) ($row->is_default ?? false),
            isActive: (bool) ($row->is_active ?? false),
            effectiveFrom: $this->dateValue($row->effective_from ?? null),
            effectiveTo: $this->dateValue($row->effective_to ?? null),
            itemCount: $this->intValue($row->item_count ?? null, count($items)),
            items: $items,
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function toItemData(stdClass $row, string $currency): PriceListItemData
    {
        return new PriceListItemData(
            priceListItemId: $this->stringValue($row->id ?? null),
            priceListId: $this->stringValue($row->price_list_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            serviceId: $this->stringValue($row->service_id ?? null),
            serviceCode: $this->stringValue($row->service_code ?? null),
            serviceName: $this->stringValue($row->service_name ?? null),
            serviceCategory: $this->nullableString($row->service_category ?? null),
            serviceUnit: $this->nullableString($row->service_unit ?? null),
            serviceIsActive: (bool) ($row->service_is_active ?? false),
            amount: $this->decimalString($row->unit_price_amount ?? null),
            currency: $currency,
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

    private function dateValue(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }

    private function decimalString(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return str_contains($value, '.')
                ? $value
                : $value.'.00';
        }

        if (is_int($value)) {
            return sprintf('%d.00', $value);
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        return '0.00';
    }

    private function intValue(mixed $value, int $fallback): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $fallback;
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
