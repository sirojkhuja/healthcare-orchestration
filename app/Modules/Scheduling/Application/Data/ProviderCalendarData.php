<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderCalendarData
{
    /**
     * @param  list<ProviderCalendarDayData>  $days
     */
    public function __construct(
        public string $providerId,
        public string $timezone,
        public string $dateFrom,
        public string $dateTo,
        public int $slotDurationMinutes,
        public int $slotIntervalMinutes,
        public CarbonImmutable $generatedAt,
        public array $days,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'timezone' => $this->timezone,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'slot_duration_minutes' => $this->slotDurationMinutes,
            'slot_interval_minutes' => $this->slotIntervalMinutes,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'days' => array_map(
                static fn (ProviderCalendarDayData $day): array => $day->toArray(),
                $this->days,
            ),
        ];
    }
}
