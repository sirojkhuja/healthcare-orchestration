<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClinicSettingsData
{
    public function __construct(
        public ?string $timezone = null,
        public int $defaultAppointmentDurationMinutes = 30,
        public int $slotIntervalMinutes = 15,
        public bool $allowWalkIns = true,
        public bool $requireAppointmentConfirmation = false,
        public bool $telemedicineEnabled = false,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timezone' => $this->timezone,
            'default_appointment_duration_minutes' => $this->defaultAppointmentDurationMinutes,
            'slot_interval_minutes' => $this->slotIntervalMinutes,
            'allow_walk_ins' => $this->allowWalkIns,
            'require_appointment_confirmation' => $this->requireAppointmentConfirmation,
            'telemedicine_enabled' => $this->telemedicineEnabled,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
