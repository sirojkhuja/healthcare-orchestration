<?php

namespace App\Modules\Treatment\Application\Contracts;

use App\Modules\Treatment\Application\Data\TreatmentItemData;

interface TreatmentItemRepository
{
    /**
     * @param  array{
     *     item_type: string,
     *     title: string,
     *     description: ?string,
     *     instructions: ?string,
     *     sort_order: int
     * }  $attributes
     */
    public function create(string $tenantId, string $planId, array $attributes): TreatmentItemData;

    public function delete(string $tenantId, string $planId, string $itemId): bool;

    public function findInPlan(string $tenantId, string $planId, string $itemId): ?TreatmentItemData;

    /**
     * @return list<TreatmentItemData>
     */
    public function listForPlan(string $tenantId, string $planId): array;

    /**
     * @param  array{
     *     item_type?: string,
     *     title?: string,
     *     description?: ?string,
     *     instructions?: ?string,
     *     sort_order?: int
     * }  $updates
     */
    public function update(string $tenantId, string $planId, string $itemId, array $updates): ?TreatmentItemData;
}
