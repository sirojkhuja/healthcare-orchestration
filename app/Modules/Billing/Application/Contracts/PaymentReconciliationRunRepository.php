<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;

interface PaymentReconciliationRunRepository
{
    /**
     * @param  array{
     *     provider_key: string,
     *     requested_payment_ids: list<string>,
     *     scanned_count: int,
     *     changed_count: int,
     *     result_count: int,
     *     results: list<array<string, mixed>>
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): PaymentReconciliationRunData;

    public function findInTenant(string $tenantId, string $runId): ?PaymentReconciliationRunData;

    /**
     * @return list<PaymentReconciliationRunData>
     */
    public function listInTenant(string $tenantId, ?string $providerKey = null, int $limit = 25): array;
}
