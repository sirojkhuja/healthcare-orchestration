<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class AppointmentRescheduleData
{
    public function __construct(
        public AppointmentData $appointment,
        public AppointmentData $replacementAppointment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'appointment' => $this->appointment->toArray(),
            'replacement_appointment' => $this->replacementAppointment->toArray(),
        ];
    }
}
