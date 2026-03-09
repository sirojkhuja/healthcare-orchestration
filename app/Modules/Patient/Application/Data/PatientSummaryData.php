<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientSummaryData
{
    public function __construct(
        public PatientData $patient,
        public string $displayName,
        public string $initials,
        public int $ageYears,
        public string $directoryStatus,
        public int $timelineEventCount,
        public CarbonImmutable $lastActivityAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'patient' => $this->patient->toArray(),
            'display_name' => $this->displayName,
            'initials' => $this->initials,
            'age_years' => $this->ageYears,
            'directory_status' => $this->directoryStatus,
            'contact' => [
                'email' => $this->patient->email,
                'phone' => $this->patient->phone,
                'has_email' => $this->patient->email !== null,
                'has_phone' => $this->patient->phone !== null,
            ],
            'location' => [
                'city_code' => $this->patient->cityCode,
                'district_code' => $this->patient->districtCode,
                'address_line_1' => $this->patient->addressLine1,
                'address_line_2' => $this->patient->addressLine2,
                'postal_code' => $this->patient->postalCode,
            ],
            'timeline_event_count' => $this->timelineEventCount,
            'last_activity_at' => $this->lastActivityAt->toIso8601String(),
        ];
    }
}
