<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Domain\Appointments\Appointment;
use App\Modules\Scheduling\Domain\Appointments\AppointmentActor;
use App\Modules\Scheduling\Domain\Appointments\AppointmentSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ScheduledAppointmentCreator
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {}

    public function create(
        string $tenantId,
        string $patientId,
        string $providerId,
        ?string $clinicId,
        ?string $roomId,
        CarbonImmutable $scheduledStartAt,
        CarbonImmutable $scheduledEndAt,
        string $timezone,
        AppointmentActor $actor,
        CarbonImmutable $occurredAt,
        ?string $recurrenceId = null,
        ?string $appointmentId = null,
    ): AppointmentData {
        $aggregate = Appointment::draft(
            appointmentId: $appointmentId ?? (string) Str::uuid(),
            tenantId: $tenantId,
            patientId: $patientId,
            providerId: $providerId,
            clinicId: $clinicId,
            roomId: $roomId,
            scheduledSlot: new AppointmentSlot(
                $scheduledStartAt->toDateTimeImmutable(),
                $scheduledEndAt->toDateTimeImmutable(),
                $timezone,
            ),
        );
        $aggregate->schedule($occurredAt->toDateTimeImmutable(), $actor);
        $snapshot = $aggregate->snapshot();

        return $this->appointmentRepository->create($tenantId, [
            'id' => $snapshot['appointment_id'],
            'patient_id' => $snapshot['patient_id'],
            'provider_id' => $snapshot['provider_id'],
            'clinic_id' => $snapshot['clinic_id'],
            'room_id' => $snapshot['room_id'],
            'recurrence_id' => $recurrenceId,
            'status' => $snapshot['status'],
            'scheduled_start_at' => CarbonImmutable::parse($snapshot['scheduled_slot']['start_at']),
            'scheduled_end_at' => CarbonImmutable::parse($snapshot['scheduled_slot']['end_at']),
            'timezone' => $snapshot['scheduled_slot']['timezone'],
            'last_transition' => $snapshot['last_transition'],
        ]);
    }
}
