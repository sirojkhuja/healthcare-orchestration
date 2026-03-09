<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClinicWorkHoursData
{
    /**
     * @param  array<string, list<array{start_time: string, end_time: string}>>  $days
     */
    public function __construct(
        public string $clinicId,
        public array $days,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clinic_id' => $this->clinicId,
            'days' => $this->days,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
