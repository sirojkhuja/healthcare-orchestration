<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class WaitlistOfferData
{
    public function __construct(
        public WaitlistEntryData $entry,
        public AppointmentData $appointment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry' => $this->entry->toArray(),
            'appointment' => $this->appointment->toArray(),
        ];
    }
}
