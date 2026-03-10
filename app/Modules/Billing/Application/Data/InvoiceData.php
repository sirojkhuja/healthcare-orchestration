<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class InvoiceData
{
    /**
     * @param  list<InvoiceItemData>  $items
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $invoiceId,
        public string $tenantId,
        public string $invoiceNumber,
        public string $patientId,
        public string $patientDisplayName,
        public ?string $priceListId,
        public ?string $priceListCode,
        public ?string $priceListName,
        public string $currency,
        public CarbonImmutable $invoiceDate,
        public ?CarbonImmutable $dueOn,
        public ?string $notes,
        public string $status,
        public string $subtotalAmount,
        public string $totalAmount,
        public int $itemCount,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $finalizedAt,
        public ?CarbonImmutable $voidedAt,
        public ?string $voidReason,
        public ?array $lastTransition,
        public ?CarbonImmutable $deletedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public array $items = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->invoiceId,
            'tenant_id' => $this->tenantId,
            'invoice_number' => $this->invoiceNumber,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'price_list' => $this->priceListId === null ? null : [
                'id' => $this->priceListId,
                'code' => $this->priceListCode,
                'name' => $this->priceListName,
            ],
            'currency' => $this->currency,
            'invoice_date' => $this->invoiceDate->toDateString(),
            'due_on' => $this->dueOn?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            'item_count' => $this->itemCount,
            'totals' => [
                'subtotal' => [
                    'amount' => $this->subtotalAmount,
                    'currency' => $this->currency,
                ],
                'total' => [
                    'amount' => $this->totalAmount,
                    'currency' => $this->currency,
                ],
            ],
            'issued_at' => $this->issuedAt?->toIso8601String(),
            'finalized_at' => $this->finalizedAt?->toIso8601String(),
            'voided_at' => $this->voidedAt?->toIso8601String(),
            'void_reason' => $this->voidReason,
            'last_transition' => $this->lastTransition,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'items' => array_map(
                static fn (InvoiceItemData $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
