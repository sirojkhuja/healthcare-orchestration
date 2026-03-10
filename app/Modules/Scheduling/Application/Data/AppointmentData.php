<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentData
{
    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $appointmentId,
        public string $tenantId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public ?string $clinicId,
        public ?string $clinicName,
        public ?string $roomId,
        public ?string $roomName,
        public string $status,
        public CarbonImmutable $scheduledStartAt,
        public CarbonImmutable $scheduledEndAt,
        public string $timezone,
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
            'id' => $this->appointmentId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'provider' => [
                'id' => $this->providerId,
                'display_name' => $this->providerDisplayName,
            ],
            'clinic' => $this->clinicId !== null
                ? [
                    'id' => $this->clinicId,
                    'name' => $this->clinicName,
                ]
                : null,
            'room' => $this->roomId !== null
                ? [
                    'id' => $this->roomId,
                    'name' => $this->roomName,
                ]
                : null,
            'scheduled_slot' => [
                'start_at' => $this->scheduledStartAt->toIso8601String(),
                'end_at' => $this->scheduledEndAt->toIso8601String(),
                'timezone' => $this->timezone,
            ],
            'last_transition' => $this->lastTransition,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
