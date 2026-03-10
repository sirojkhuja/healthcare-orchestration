<?php

namespace App\Modules\Treatment\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EncounterData
{
    public function __construct(
        public string $encounterId,
        public string $tenantId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $treatmentPlanId,
        public ?string $appointmentId,
        public ?string $clinicId,
        public ?string $clinicName,
        public ?string $roomId,
        public ?string $roomName,
        public string $status,
        public CarbonImmutable $encounteredAt,
        public string $timezone,
        public ?string $chiefComplaint,
        public ?string $summary,
        public ?string $notes,
        public ?string $followUpInstructions,
        public int $diagnosisCount,
        public int $procedureCount,
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
            'id' => $this->encounterId,
            'tenant_id' => $this->tenantId,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'provider' => [
                'id' => $this->providerId,
                'display_name' => $this->providerDisplayName,
            ],
            'treatment_plan_id' => $this->treatmentPlanId,
            'appointment_id' => $this->appointmentId,
            'clinic' => $this->clinicId === null ? null : [
                'id' => $this->clinicId,
                'name' => $this->clinicName,
            ],
            'room' => $this->roomId === null ? null : [
                'id' => $this->roomId,
                'name' => $this->roomName,
            ],
            'status' => $this->status,
            'encountered_at' => $this->encounteredAt->toIso8601String(),
            'timezone' => $this->timezone,
            'chief_complaint' => $this->chiefComplaint,
            'summary' => $this->summary,
            'notes' => $this->notes,
            'follow_up_instructions' => $this->followUpInstructions,
            'diagnosis_count' => $this->diagnosisCount,
            'procedure_count' => $this->procedureCount,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
