<?php

namespace App\Modules\Treatment\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TreatmentItemData
{
    public function __construct(
        public string $itemId,
        public string $planId,
        public string $tenantId,
        public string $itemType,
        public string $title,
        public ?string $description,
        public ?string $instructions,
        public int $sortOrder,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->itemId,
            'plan_id' => $this->planId,
            'tenant_id' => $this->tenantId,
            'item_type' => $this->itemType,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
