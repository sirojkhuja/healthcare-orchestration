<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AvailabilityRuleData
{
    public function __construct(
        public string $ruleId,
        public string $tenantId,
        public string $providerId,
        public string $scopeType,
        public string $availabilityType,
        public ?string $weekday,
        public ?CarbonImmutable $specificDate,
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
            'id' => $this->ruleId,
            'tenant_id' => $this->tenantId,
            'provider_id' => $this->providerId,
            'scope_type' => $this->scopeType,
            'availability_type' => $this->availabilityType,
            'weekday' => $this->weekday,
            'specific_date' => $this->specificDate?->toDateString(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
