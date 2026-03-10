<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Data\PriceListListCriteria;

interface PriceListRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): PriceListData;

    public function delete(string $tenantId, string $priceListId): bool;

    public function findInTenant(string $tenantId, string $priceListId): ?PriceListData;

    /**
     * @return list<PriceListData>
     */
    public function listDefaultsForTenant(string $tenantId, ?string $ignorePriceListId = null): array;

    /**
     * @return list<PriceListData>
     */
    public function listForTenant(string $tenantId, PriceListListCriteria $criteria): array;

    public function clearDefaultFlags(string $tenantId, ?string $ignorePriceListId = null): void;

    public function codeExists(string $tenantId, string $code, ?string $ignorePriceListId = null): bool;

    /**
     * @param  list<array{service_id: string, amount: string}>  $items
     */
    public function replaceItems(string $tenantId, string $priceListId, array $items): void;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $priceListId, array $updates): ?PriceListData;
}
