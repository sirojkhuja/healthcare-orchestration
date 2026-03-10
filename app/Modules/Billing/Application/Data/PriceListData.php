<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PriceListData
{
    /**
     * @param  list<PriceListItemData>  $items
     */
    public function __construct(
        public string $priceListId,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $description,
        public string $currency,
        public bool $isDefault,
        public bool $isActive,
        public ?CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo,
        public int $itemCount,
        public array $items,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->priceListId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
            'effective_from' => $this->effectiveFrom?->toDateString(),
            'effective_to' => $this->effectiveTo?->toDateString(),
            'item_count' => $this->itemCount,
            'items' => array_map(
                static fn (PriceListItemData $item): array => $item->toArray(),
                $this->items,
            ),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
