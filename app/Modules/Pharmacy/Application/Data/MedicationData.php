<?php

namespace App\Modules\Pharmacy\Application\Data;

use Carbon\CarbonImmutable;

final readonly class MedicationData
{
    public function __construct(
        public string $medicationId,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $genericName,
        public ?string $form,
        public ?string $strength,
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
            'id' => $this->medicationId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'generic_name' => $this->genericName,
            'form' => $this->form,
            'strength' => $this->strength,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
