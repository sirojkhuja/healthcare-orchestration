<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientContactData
{
    public function __construct(
        public string $contactId,
        public string $patientId,
        public string $name,
        public ?string $relationship,
        public ?string $phone,
        public ?string $email,
        public bool $isPrimary,
        public bool $isEmergency,
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
            'id' => $this->contactId,
            'patient_id' => $this->patientId,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_primary' => $this->isPrimary,
            'is_emergency' => $this->isEmergency,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
