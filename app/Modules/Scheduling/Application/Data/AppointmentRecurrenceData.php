<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentRecurrenceData
{
    public function __construct(
        public string $recurrenceId,
        public string $sourceAppointmentId,
        public string $patientId,
        public string $providerId,
        public ?string $clinicId,
        public ?string $roomId,
        public string $frequency,
        public int $interval,
        public ?int $occurrenceCount,
        public ?CarbonImmutable $untilDate,
        public string $timezone,
        public string $status,
        public ?string $canceledReason,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->recurrenceId,
            'source_appointment_id' => $this->sourceAppointmentId,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'room_id' => $this->roomId,
            'frequency' => $this->frequency,
            'interval' => $this->interval,
            'occurrence_count' => $this->occurrenceCount,
            'until_date' => $this->untilDate?->toDateString(),
            'timezone' => $this->timezone,
            'status' => $this->status,
            'canceled_reason' => $this->canceledReason,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
