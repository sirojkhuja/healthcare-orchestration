<?php

namespace App\Modules\Lab\Application\Contracts;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;
use Carbon\CarbonImmutable;

interface LabOrderRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     lab_test_id: ?string,
     *     lab_provider_key: string,
     *     requested_test_code: string,
     *     requested_test_name: string,
     *     requested_specimen_type: string,
     *     requested_result_type: string,
     *     status: string,
     *     ordered_at: CarbonImmutable,
     *     timezone: string,
     *     notes: ?string,
     *     external_order_id: ?string,
     *     sent_at: ?CarbonImmutable,
     *     specimen_collected_at: ?CarbonImmutable,
     *     specimen_received_at: ?CarbonImmutable,
     *     completed_at: ?CarbonImmutable,
     *     canceled_at: ?CarbonImmutable,
     *     cancel_reason: ?string,
     *     last_transition: array<string, mixed>|null
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): LabOrderData;

    public function findByExternalOrderId(string $labProviderKey, string $externalOrderId): ?LabOrderData;

    public function findInTenant(string $tenantId, string $orderId, bool $withDeleted = false): ?LabOrderData;

    /**
     * @param  list<string>  $orderIds
     * @return list<LabOrderData>
     */
    public function findManyInTenant(string $tenantId, array $orderIds, bool $withDeleted = false): array;

    /**
     * @return list<LabOrderData>
     */
    public function search(string $tenantId, LabOrderSearchCriteria $criteria): array;

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $orderIds
     * @return list<LabOrderData>
     */
    public function listForReconciliation(
        string $tenantId,
        string $labProviderKey,
        array $statuses,
        int $limit,
        array $orderIds = [],
    ): array;

    public function softDelete(string $tenantId, string $orderId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $orderId, array $updates): ?LabOrderData;
}
