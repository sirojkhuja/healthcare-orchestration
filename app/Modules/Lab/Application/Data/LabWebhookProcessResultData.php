<?php

namespace App\Modules\Lab\Application\Data;

final readonly class LabWebhookProcessResultData
{
    /**
     * @param  list<LabResultData>  $results
     */
    public function __construct(
        public string $providerKey,
        public string $deliveryId,
        public bool $alreadyProcessed,
        public LabOrderData $order,
        public array $results,
        public LabWebhookDeliveryData $delivery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'delivery_id' => $this->deliveryId,
            'already_processed' => $this->alreadyProcessed,
            'order' => $this->order->toArray(),
            'result_count' => count($this->results),
            'results' => array_map(
                static fn (LabResultData $result): array => $result->toArray(),
                $this->results,
            ),
            'delivery' => $this->delivery->toArray(),
        ];
    }
}
