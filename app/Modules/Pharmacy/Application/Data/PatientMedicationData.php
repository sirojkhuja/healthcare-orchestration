<?php

namespace App\Modules\Pharmacy\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientMedicationData
{
    public function __construct(
        public string $prescriptionId,
        public string $status,
        public string $medicationName,
        public ?string $medicationCode,
        public ?string $catalogMedicationId,
        public ?string $catalogCode,
        public ?string $catalogName,
        public ?string $catalogGenericName,
        public ?string $catalogForm,
        public ?string $catalogStrength,
        public ?bool $catalogIsActive,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $encounterId,
        public ?string $encounterSummary,
        public ?string $treatmentItemId,
        public ?string $treatmentItemTitle,
        public string $dosage,
        public string $route,
        public string $frequency,
        public string $quantity,
        public ?string $quantityUnit,
        public int $authorizedRefills,
        public ?string $instructions,
        public ?string $notes,
        public ?string $startsOn,
        public ?string $endsOn,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $dispensedAt,
        public ?CarbonImmutable $canceledAt,
        public ?string $cancelReason,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prescription_id' => $this->prescriptionId,
            'status' => $this->status,
            'medication' => [
                'name' => $this->medicationName,
                'code' => $this->medicationCode,
                'catalog' => $this->catalogMedicationId === null ? null : [
                    'id' => $this->catalogMedicationId,
                    'code' => $this->catalogCode,
                    'name' => $this->catalogName,
                    'generic_name' => $this->catalogGenericName,
                    'form' => $this->catalogForm,
                    'strength' => $this->catalogStrength,
                    'is_active' => $this->catalogIsActive,
                ],
            ],
            'provider' => [
                'id' => $this->providerId,
                'display_name' => $this->providerDisplayName,
            ],
            'encounter' => $this->encounterId === null ? null : [
                'id' => $this->encounterId,
                'summary' => $this->encounterSummary,
            ],
            'treatment_item' => $this->treatmentItemId === null ? null : [
                'id' => $this->treatmentItemId,
                'title' => $this->treatmentItemTitle,
            ],
            'dosage' => $this->dosage,
            'route' => $this->route,
            'frequency' => $this->frequency,
            'quantity' => $this->quantity,
            'quantity_unit' => $this->quantityUnit,
            'authorized_refills' => $this->authorizedRefills,
            'instructions' => $this->instructions,
            'notes' => $this->notes,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'issued_at' => $this->issuedAt?->toIso8601String(),
            'dispensed_at' => $this->dispensedAt?->toIso8601String(),
            'canceled_at' => $this->canceledAt?->toIso8601String(),
            'cancel_reason' => $this->cancelReason,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
