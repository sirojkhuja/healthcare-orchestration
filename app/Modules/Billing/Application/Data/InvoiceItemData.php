<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class InvoiceItemData
{
    public function __construct(
        public string $invoiceItemId,
        public string $tenantId,
        public string $invoiceId,
        public string $serviceId,
        public string $serviceCode,
        public string $serviceName,
        public ?string $serviceCategory,
        public ?string $serviceUnit,
        public ?string $description,
        public string $quantity,
        public string $unitPriceAmount,
        public string $lineSubtotalAmount,
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
            'id' => $this->invoiceItemId,
            'invoice_id' => $this->invoiceId,
            'tenant_id' => $this->tenantId,
            'service' => [
                'id' => $this->serviceId,
                'code' => $this->serviceCode,
                'name' => $this->serviceName,
                'category' => $this->serviceCategory,
                'unit' => $this->serviceUnit,
            ],
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => [
                'amount' => $this->unitPriceAmount,
                'currency' => $this->currency,
            ],
            'line_subtotal' => [
                'amount' => $this->lineSubtotalAmount,
                'currency' => $this->currency,
            ],
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
