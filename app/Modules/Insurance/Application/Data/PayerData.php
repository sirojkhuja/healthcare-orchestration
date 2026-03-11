<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PayerData
{
    public function __construct(
        public string $payerId,
        public string $tenantId,
        public string $code,
        public string $name,
        public string $insuranceCode,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public bool $isActive,
        public ?string $notes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->payerId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'insurance_code' => $this->insuranceCode,
            'contact' => [
                'name' => $this->contactName,
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone,
            ],
            'is_active' => $this->isActive,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
