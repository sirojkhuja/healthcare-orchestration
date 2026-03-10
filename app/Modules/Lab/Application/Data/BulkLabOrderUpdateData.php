<?php

namespace App\Modules\Lab\Application\Data;

final readonly class BulkLabOrderUpdateData
{
    /**
     * @param  list<string>  $updatedFields
     * @param  list<LabOrderData>  $orders
     */
    public function __construct(
        public string $operationId,
        public int $affectedCount,
        public array $updatedFields,
        public array $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'affected_count' => $this->affectedCount,
            'updated_fields' => $this->updatedFields,
            'orders' => array_map(
                static fn (LabOrderData $order): array => $order->toArray(),
                $this->orders,
            ),
        ];
    }
}
