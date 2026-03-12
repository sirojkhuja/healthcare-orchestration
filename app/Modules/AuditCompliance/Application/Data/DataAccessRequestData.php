<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class DataAccessRequestData
{
    public function __construct(
        public string $requestId,
        public string $patientId,
        public string $patientDisplayName,
        public string $requestType,
        public string $status,
        public string $requestedByName,
        public ?string $requestedByRelationship,
        public CarbonImmutable $requestedAt,
        public ?string $reason,
        public ?string $notes,
        public ?CarbonImmutable $approvedAt,
        public ?string $approvedByUserId,
        public ?string $approvedByName,
        public ?CarbonImmutable $deniedAt,
        public ?string $deniedByUserId,
        public ?string $deniedByName,
        public ?string $denialReason,
        public ?string $decisionNotes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->requestId,
            'patient_id' => $this->patientId,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'request_type' => $this->requestType,
            'status' => $this->status,
            'requested_by_name' => $this->requestedByName,
            'requested_by_relationship' => $this->requestedByRelationship,
            'requested_at' => $this->requestedAt->toIso8601String(),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'approved_at' => $this->approvedAt?->toIso8601String(),
            'approved_by' => $this->reviewerArray($this->approvedByUserId, $this->approvedByName),
            'denied_at' => $this->deniedAt?->toIso8601String(),
            'denied_by' => $this->reviewerArray($this->deniedByUserId, $this->deniedByName),
            'denial_reason' => $this->denialReason,
            'decision_notes' => $this->decisionNotes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @return array{id: ?string, name: ?string}|null
     */
    private function reviewerArray(?string $userId, ?string $name): ?array
    {
        if ($userId === null && $name === null) {
            return null;
        }

        return [
            'id' => $userId,
            'name' => $name,
        ];
    }
}
