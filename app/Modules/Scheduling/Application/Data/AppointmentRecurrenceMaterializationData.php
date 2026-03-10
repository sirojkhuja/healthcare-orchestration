<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class AppointmentRecurrenceMaterializationData
{
    /**
     * @param  list<AppointmentData>  $appointments
     */
    public function __construct(
        public AppointmentRecurrenceData $recurrence,
        public array $appointments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'recurrence' => $this->recurrence->toArray(),
            'appointments' => array_map(
                static fn (AppointmentData $appointment): array => $appointment->toArray(),
                $this->appointments,
            ),
        ];
    }
}
