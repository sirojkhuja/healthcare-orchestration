<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClinicHolidayData
{
    public function __construct(
        public string $holidayId,
        public string $clinicId,
        public string $name,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public bool $isClosed,
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
            'id' => $this->holidayId,
            'clinic_id' => $this->clinicId,
            'name' => $this->name,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'is_closed' => $this->isClosed,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
