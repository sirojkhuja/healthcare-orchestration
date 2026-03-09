<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderTimeOffData
{
    public function __construct(
        public string $timeOffId,
        public string $providerId,
        public CarbonImmutable $specificDate,
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
            'provider_id' => $this->providerId,
            'specific_date' => $this->specificDate->toDateString(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
