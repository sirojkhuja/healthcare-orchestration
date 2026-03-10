<?php

namespace App\Modules\Pharmacy\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PrescriptionData
{
    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $prescriptionId,
        public string $tenantId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $encounterId,
        public ?string $encounterSummary,
        public ?string $treatmentItemId,
        public ?string $treatmentItemTitle,
        public string $medicationName,
        public ?string $medicationCode,
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
        public string $status,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $dispensedAt,
        public ?CarbonImmutable $canceledAt,
        public ?string $cancelReason,
        public ?array $lastTransition,
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
            'id' => $this->prescriptionId,
            'tenant_id' => $this->tenantId,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
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
            'medication' => [
                'name' => $this->medicationName,
                'code' => $this->medicationCode,
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
            'status' => $this->status,
            'issued_at' => $this->issuedAt?->toIso8601String(),
            'dispensed_at' => $this->dispensedAt?->toIso8601String(),
            'canceled_at' => $this->canceledAt?->toIso8601String(),
            'cancel_reason' => $this->cancelReason,
            'last_transition' => $this->lastTransition,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
