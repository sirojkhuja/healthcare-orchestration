<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AvailabilitySlotData
{
    /**
     * @param  list<string>  $sourceRuleIds
     */
    public function __construct(
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
        public string $date,
        public array $sourceRuleIds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_at' => $this->startAt->toIso8601String(),
            'end_at' => $this->endAt->toIso8601String(),
            'date' => $this->date,
            'source_rule_ids' => $this->sourceRuleIds,
        ];
    }
}
