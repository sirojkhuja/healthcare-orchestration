<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class BulkAppointmentTransitionData
{
    /**
     * @param  list<AppointmentData>  $appointments
     * @param  list<AppointmentData>  $replacementAppointments
     */
    public function __construct(
        public string $operationId,
        public int $affectedCount,
        public array $appointments,
        public array $replacementAppointments = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'affected_count' => $this->affectedCount,
            'appointments' => array_map(
                static fn (AppointmentData $appointment): array => $appointment->toArray(),
                $this->appointments,
            ),
            'replacement_appointments' => array_map(
                static fn (AppointmentData $appointment): array => $appointment->toArray(),
                $this->replacementAppointments,
            ),
        ];
    }
}
