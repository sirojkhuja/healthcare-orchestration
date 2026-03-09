<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderData
{
    public function __construct(
        public string $providerId,
        public string $tenantId,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $preferredName,
        public string $providerType,
        public ?string $email,
        public ?string $phone,
        public ?string $clinicId,
        public ?string $notes,
        public ?CarbonImmutable $deletedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->providerId,
            'tenant_id' => $this->tenantId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'middle_name' => $this->middleName,
            'preferred_name' => $this->preferredName,
            'provider_type' => $this->providerType,
            'email' => $this->email,
            'phone' => $this->phone,
            'clinic_id' => $this->clinicId,
            'notes' => $this->notes,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
