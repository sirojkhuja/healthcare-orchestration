<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class BillableServiceData
{
    public function __construct(
        public string $serviceId,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $category,
        public ?string $unit,
        public ?string $description,
        public bool $isActive,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->serviceId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
