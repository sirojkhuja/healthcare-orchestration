<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderSpecialtyData
{
    public function __construct(
        public string $providerId,
        public string $specialtyId,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public bool $isPrimary,
        public CarbonImmutable $assignedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'specialty_id' => $this->specialtyId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'is_primary' => $this->isPrimary,
            'assigned_at' => $this->assignedAt->toIso8601String(),
        ];
    }
}
