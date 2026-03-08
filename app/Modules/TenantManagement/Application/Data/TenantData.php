<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TenantData
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $status,
        public ?string $membershipStatus,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?CarbonImmutable $activatedAt,
        public ?CarbonImmutable $suspendedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     membership_status: string|null,
     *     contact_email: string|null,
     *     contact_phone: string|null,
     *     activated_at: string|null,
     *     suspended_at: string|null,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->tenantId,
            'name' => $this->name,
            'status' => $this->status,
            'membership_status' => $this->membershipStatus,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'activated_at' => $this->activatedAt?->toIso8601String(),
            'suspended_at' => $this->suspendedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
