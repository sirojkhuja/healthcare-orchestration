<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class SpecialtyData
{
    public function __construct(
        public string $specialtyId,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->specialtyId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
