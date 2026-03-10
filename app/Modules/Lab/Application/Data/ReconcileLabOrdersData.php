<?php

namespace App\Modules\Lab\Application\Data;

final readonly class ReconcileLabOrdersData
{
    /**
     * @param  list<LabOrderData>  $orders
     */
    public function __construct(
        public string $operationId,
        public string $labProviderKey,
        public int $affectedCount,
        public int $resultCount,
        public array $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'lab_provider_key' => $this->labProviderKey,
            'affected_count' => $this->affectedCount,
            'result_count' => $this->resultCount,
            'orders' => array_map(
                static fn (LabOrderData $order): array => $order->toArray(),
                $this->orders,
            ),
        ];
    }
}
