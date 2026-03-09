<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderCalendarTimeOffData
{
    public function __construct(
        public string $timeOffId,
        public string $specificDate,
        public string $startTime,
        public string $endTime,
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
            'id' => $this->timeOffId,
            'specific_date' => $this->specificDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
