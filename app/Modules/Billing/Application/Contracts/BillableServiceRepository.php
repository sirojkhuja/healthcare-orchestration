<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Data\BillableServiceListCriteria;

interface BillableServiceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): BillableServiceData;

    public function delete(string $tenantId, string $serviceId): bool;

    public function findInTenant(string $tenantId, string $serviceId): ?BillableServiceData;

    /**
     * @param  list<string>  $serviceIds
     * @return list<BillableServiceData>
     */
    public function listByIds(string $tenantId, array $serviceIds): array;

    /**
     * @return list<BillableServiceData>
     */
    public function listForTenant(string $tenantId, BillableServiceListCriteria $criteria): array;

    public function codeExists(string $tenantId, string $code, ?string $ignoreServiceId = null): bool;

    public function isReferencedInPriceLists(string $tenantId, string $serviceId): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $serviceId, array $updates): ?BillableServiceData;
}
