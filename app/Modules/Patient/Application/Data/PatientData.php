<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientData
{
    public function __construct(
        public string $patientId,
        public string $tenantId,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $preferredName,
        public string $sex,
        public CarbonImmutable $birthDate,
        public ?string $nationalId,
        public ?string $email,
        public ?string $phone,
        public ?string $cityCode,
        public ?string $districtCode,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $postalCode,
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
            'id' => $this->patientId,
            'tenant_id' => $this->tenantId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'middle_name' => $this->middleName,
            'preferred_name' => $this->preferredName,
            'sex' => $this->sex,
            'birth_date' => $this->birthDate->toDateString(),
            'national_id' => $this->nationalId,
            'email' => $this->email,
            'phone' => $this->phone,
            'city_code' => $this->cityCode,
            'district_code' => $this->districtCode,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'notes' => $this->notes,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
