<?php

namespace App\Modules\Pharmacy\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientAllergyData
{
    public function __construct(
        public string $allergyId,
        public string $patientId,
        public ?string $medicationId,
        public ?string $medicationCode,
        public ?string $medicationName,
        public ?string $medicationGenericName,
        public ?string $medicationForm,
        public ?string $medicationStrength,
        public ?bool $medicationIsActive,
        public string $allergenName,
        public ?string $reaction,
        public ?string $severity,
        public ?CarbonImmutable $notedAt,
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
            'id' => $this->allergyId,
            'patient_id' => $this->patientId,
            'medication' => $this->medicationId === null ? null : [
                'id' => $this->medicationId,
                'code' => $this->medicationCode,
                'name' => $this->medicationName,
                'generic_name' => $this->medicationGenericName,
                'form' => $this->medicationForm,
                'strength' => $this->medicationStrength,
                'is_active' => $this->medicationIsActive,
            ],
            'allergen_name' => $this->allergenName,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'noted_at' => $this->notedAt?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
