<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentReconciliationRunData
{
    /**
     * @param  list<string>  $requestedPaymentIds
     * @param  list<PaymentReconciliationResultData>  $results
     */
    public function __construct(
        public string $runId,
        public string $tenantId,
        public string $providerKey,
        public array $requestedPaymentIds,
        public int $scannedCount,
        public int $changedCount,
        public int $resultCount,
        public array $results,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->runId,
            'tenant_id' => $this->tenantId,
            'provider_key' => $this->providerKey,
            'requested_payment_ids' => $this->requestedPaymentIds,
            'scanned_count' => $this->scannedCount,
            'changed_count' => $this->changedCount,
            'result_count' => $this->resultCount,
            'results' => array_map(
                static fn (PaymentReconciliationResultData $result): array => $result->toArray(),
                $this->results,
            ),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
