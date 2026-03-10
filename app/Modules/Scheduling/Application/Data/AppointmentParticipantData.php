<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentParticipantData
{
    public function __construct(
        public string $participantId,
        public string $appointmentId,
        public string $participantType,
        public ?string $referenceId,
        public string $displayName,
        public string $role,
        public bool $required,
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
            'id' => $this->participantId,
            'appointment_id' => $this->appointmentId,
            'participant_type' => $this->participantType,
            'reference_id' => $this->referenceId,
            'display_name' => $this->displayName,
            'role' => $this->role,
            'required' => $this->required,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
