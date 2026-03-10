<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class WaitlistEntryData
{
    /**
     * @param  array<string, mixed>|null  $offeredSlot
     */
    public function __construct(
        public string $entryId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $clinicId,
        public ?string $clinicName,
        public ?string $roomId,
        public ?string $roomName,
        public string $desiredDateFrom,
        public string $desiredDateTo,
        public ?string $preferredStartTime,
        public ?string $preferredEndTime,
        public ?string $notes,
        public string $status,
        public ?string $bookedAppointmentId,
        public ?array $offeredSlot,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->entryId,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'provider' => [
                'id' => $this->providerId,
                'display_name' => $this->providerDisplayName,
            ],
            'clinic' => $this->clinicId !== null ? [
                'id' => $this->clinicId,
                'name' => $this->clinicName,
            ] : null,
            'room' => $this->roomId !== null ? [
                'id' => $this->roomId,
                'name' => $this->roomName,
            ] : null,
            'desired_date_from' => $this->desiredDateFrom,
            'desired_date_to' => $this->desiredDateTo,
            'preferred_start_time' => $this->preferredStartTime,
            'preferred_end_time' => $this->preferredEndTime,
            'notes' => $this->notes,
            'status' => $this->status,
            'booked_appointment_id' => $this->bookedAppointmentId,
            'offered_slot' => $this->offeredSlot,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
