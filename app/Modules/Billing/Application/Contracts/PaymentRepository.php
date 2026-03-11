<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentListCriteria;

interface PaymentRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): PaymentData;

    public function find(string $paymentId): ?PaymentData;

    public function findInTenant(string $tenantId, string $paymentId): ?PaymentData;

    public function findByProviderPaymentId(string $providerKey, string $providerPaymentId): ?PaymentData;

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $paymentIds
     * @return list<PaymentData>
     */
    public function listForReconciliation(
        string $tenantId,
        string $providerKey,
        array $statuses,
        int $limit,
        array $paymentIds = [],
    ): array;

    /**
     * @return list<PaymentData>
     */
    public function search(string $tenantId, PaymentListCriteria $criteria): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $paymentId, array $updates): ?PaymentData;
}
