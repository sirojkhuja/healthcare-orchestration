<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Domain\Appointments\Appointment;
use App\Modules\Scheduling\Domain\Appointments\AppointmentSlot;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use App\Modules\Scheduling\Domain\Appointments\AppointmentTransitionData;

final class AppointmentAggregateMapper
{
    public function fromData(AppointmentData $appointment): Appointment
    {
        $transitionPayload = $appointment->lastTransition !== null
            ? $this->transitionPayload($appointment->lastTransition)
            : null;
        $lastTransition = $transitionPayload !== null
            ? AppointmentTransitionData::fromArray($transitionPayload)
            : null;
        $replacementAppointmentId = $lastTransition?->replacementAppointmentId;
        $replacementSlot = $lastTransition?->replacementSlot;

        return Appointment::reconstitute(
            appointmentId: $appointment->appointmentId,
            tenantId: $appointment->tenantId,
            patientId: $appointment->patientId,
            providerId: $appointment->providerId,
            clinicId: $appointment->clinicId,
            roomId: $appointment->roomId,
            scheduledSlot: new AppointmentSlot(
                $appointment->scheduledStartAt->toDateTimeImmutable(),
                $appointment->scheduledEndAt->toDateTimeImmutable(),
                $appointment->timezone,
            ),
            status: AppointmentStatus::from($appointment->status),
            lastTransition: $lastTransition,
            replacementAppointmentId: $replacementAppointmentId,
            replacementSlot: $replacementSlot,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed,
     *     admin_override?: mixed,
     *     restored_from_status?: mixed,
     *     replacement_appointment_id?: mixed,
     *     replacement_slot?: mixed
     * }
     */
    private function transitionPayload(array $payload): array
    {
        return [
            'from_status' => $payload['from_status'] ?? null,
            'to_status' => $payload['to_status'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? null,
            'actor' => $payload['actor'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'admin_override' => $payload['admin_override'] ?? null,
            'restored_from_status' => $payload['restored_from_status'] ?? null,
            'replacement_appointment_id' => $payload['replacement_appointment_id'] ?? null,
            'replacement_slot' => $payload['replacement_slot'] ?? null,
        ];
    }
}
