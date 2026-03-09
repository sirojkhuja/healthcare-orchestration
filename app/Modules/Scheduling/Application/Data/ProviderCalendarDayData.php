<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class ProviderCalendarDayData
{
    /**
     * @param  list<array{start_time: string, end_time: string}>  $workHours
     * @param  list<ProviderCalendarTimeOffData>  $timeOff
     * @param  list<AvailabilitySlotData>  $slots
     */
    public function __construct(
        public string $date,
        public string $weekday,
        public bool $isClinicClosed,
        public array $workHours,
        public array $timeOff,
        public int $slotCount,
        public array $slots,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'weekday' => $this->weekday,
            'is_clinic_closed' => $this->isClinicClosed,
            'work_hours' => $this->workHours,
            'time_off' => array_map(
                static fn (ProviderCalendarTimeOffData $timeOff): array => $timeOff->toArray(),
                $this->timeOff,
            ),
            'slot_count' => $this->slotCount,
            'slots' => array_map(
                static fn (AvailabilitySlotData $slot): array => $slot->toArray(),
                $this->slots,
            ),
        ];
    }
}
