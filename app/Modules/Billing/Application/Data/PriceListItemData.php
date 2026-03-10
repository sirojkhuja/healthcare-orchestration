<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PriceListItemData
{
    public function __construct(
        public string $priceListItemId,
        public string $priceListId,
        public string $tenantId,
        public string $serviceId,
        public string $serviceCode,
        public string $serviceName,
        public ?string $serviceCategory,
        public ?string $serviceUnit,
        public bool $serviceIsActive,
        public string $amount,
        public string $currency,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->priceListItemId,
            'price_list_id' => $this->priceListId,
            'tenant_id' => $this->tenantId,
            'service' => [
                'id' => $this->serviceId,
                'code' => $this->serviceCode,
                'name' => $this->serviceName,
                'category' => $this->serviceCategory,
                'unit' => $this->serviceUnit,
                'is_active' => $this->serviceIsActive,
            ],
            'unit_price' => [
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
