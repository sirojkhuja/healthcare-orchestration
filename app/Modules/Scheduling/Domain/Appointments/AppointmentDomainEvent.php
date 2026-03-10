<?php

namespace App\Modules\Scheduling\Domain\Appointments;

final readonly class AppointmentDomainEvent
{
    public function __construct(
        public AppointmentEventType $type,
        public string $appointmentId,
        public string $tenantId,
        public string $patientId,
        public string $providerId,
        public ?string $clinicId,
        public ?string $roomId,
        public AppointmentStatus $status,
        public AppointmentTransitionData $transition,
        public AppointmentSlot $scheduledSlot,
    ) {}

    /**
     * @return array{
     *     event_type: string,
     *     appointment_id: string,
     *     tenant_id: string,
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: string|null,
     *     room_id: string|null,
     *     status: string,
     *     transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null,
     *         admin_override: bool,
     *         restored_from_status: string|null,
     *         replacement_appointment_id: string|null,
     *         replacement_slot: array{start_at: string, end_at: string, timezone: string}|null
     *     },
     *     scheduled_slot: array{start_at: string, end_at: string, timezone: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->type->value,
            'appointment_id' => $this->appointmentId,
            'tenant_id' => $this->tenantId,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'room_id' => $this->roomId,
            'status' => $this->status->value,
            'transition' => $this->transition->toArray(),
            'scheduled_slot' => $this->scheduledSlot->toArray(),
        ];
    }
}
