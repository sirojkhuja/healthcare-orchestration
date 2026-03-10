<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class BulkAppointmentUpdateData
{
    /**
     * @param  list<string>  $updatedFields
     * @param  list<AppointmentData>  $appointments
     */
    public function __construct(
        public string $operationId,
        public int $affectedCount,
        public array $updatedFields,
        public array $appointments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'affected_count' => $this->affectedCount,
            'updated_fields' => $this->updatedFields,
            'appointments' => array_map(
                static fn (AppointmentData $appointment): array => $appointment->toArray(),
                $this->appointments,
            ),
        ];
    }
}
