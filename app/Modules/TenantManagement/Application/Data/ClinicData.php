<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClinicData
{
    public function __construct(
        public string $clinicId,
        public string $tenantId,
        public string $code,
        public string $name,
        public string $status,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $cityCode,
        public ?string $districtCode,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $postalCode,
        public ?string $notes,
        public ?CarbonImmutable $activatedAt,
        public ?CarbonImmutable $deactivatedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->clinicId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'city_code' => $this->cityCode,
            'district_code' => $this->districtCode,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'notes' => $this->notes,
            'activated_at' => $this->activatedAt?->toIso8601String(),
            'deactivated_at' => $this->deactivatedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
