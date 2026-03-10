<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabOrderData
{
    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $orderId,
        public string $tenantId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $encounterId,
        public ?string $encounterSummary,
        public ?string $treatmentItemId,
        public ?string $treatmentItemTitle,
        public ?string $labTestId,
        public string $labProviderKey,
        public string $requestedTestCode,
        public string $requestedTestName,
        public string $requestedSpecimenType,
        public string $requestedResultType,
        public string $status,
        public CarbonImmutable $orderedAt,
        public string $timezone,
        public ?string $notes,
        public ?string $externalOrderId,
        public ?CarbonImmutable $sentAt,
        public ?CarbonImmutable $specimenCollectedAt,
        public ?CarbonImmutable $specimenReceivedAt,
        public ?CarbonImmutable $completedAt,
        public ?CarbonImmutable $canceledAt,
        public ?string $cancelReason,
        public ?array $lastTransition,
        public int $resultCount,
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
            'id' => $this->orderId,
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
            'lab_test' => $this->labTestId === null ? null : [
                'id' => $this->labTestId,
            ],
            'lab_provider_key' => $this->labProviderKey,
            'requested_test' => [
                'code' => $this->requestedTestCode,
                'name' => $this->requestedTestName,
                'specimen_type' => $this->requestedSpecimenType,
                'result_type' => $this->requestedResultType,
            ],
            'status' => $this->status,
            'ordered_at' => $this->orderedAt->toIso8601String(),
            'timezone' => $this->timezone,
            'notes' => $this->notes,
            'external_order_id' => $this->externalOrderId,
            'sent_at' => $this->sentAt?->toIso8601String(),
            'specimen_collected_at' => $this->specimenCollectedAt?->toIso8601String(),
            'specimen_received_at' => $this->specimenReceivedAt?->toIso8601String(),
            'completed_at' => $this->completedAt?->toIso8601String(),
            'canceled_at' => $this->canceledAt?->toIso8601String(),
            'cancel_reason' => $this->cancelReason,
            'last_transition' => $this->lastTransition,
            'result_count' => $this->resultCount,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
