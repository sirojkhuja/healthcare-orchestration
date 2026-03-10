<?php

namespace App\Modules\Scheduling\Domain\Appointments;

use DateTimeImmutable;

final class Appointment
{
    private AppointmentStatus $status;

    private ?AppointmentTransitionData $lastTransition = null;

    private ?AppointmentSlot $replacementSlot = null;

    private ?string $replacementAppointmentId = null;

    /** @var list<AppointmentDomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private string $appointmentId,
        private string $tenantId,
        private string $patientId,
        private string $providerId,
        private ?string $clinicId,
        private ?string $roomId,
        private AppointmentSlot $scheduledSlot,
        AppointmentStatus $status,
    ) {
        $this->status = $status;
    }

    public static function draft(
        string $appointmentId,
        string $tenantId,
        string $patientId,
        string $providerId,
        AppointmentSlot $scheduledSlot,
        ?string $clinicId = null,
        ?string $roomId = null,
    ): self {
        return new self(
            appointmentId: $appointmentId,
            tenantId: $tenantId,
            patientId: $patientId,
            providerId: $providerId,
            clinicId: $clinicId,
            roomId: $roomId,
            scheduledSlot: $scheduledSlot,
            status: AppointmentStatus::DRAFT,
        );
    }

    public static function reconstitute(
        string $appointmentId,
        string $tenantId,
        string $patientId,
        string $providerId,
        AppointmentSlot $scheduledSlot,
        AppointmentStatus $status,
        ?string $clinicId = null,
        ?string $roomId = null,
        ?AppointmentTransitionData $lastTransition = null,
        ?string $replacementAppointmentId = null,
        ?AppointmentSlot $replacementSlot = null,
    ): self {
        $appointment = new self(
            appointmentId: $appointmentId,
            tenantId: $tenantId,
            patientId: $patientId,
            providerId: $providerId,
            clinicId: $clinicId,
            roomId: $roomId,
            scheduledSlot: $scheduledSlot,
            status: $status,
        );
        $appointment->lastTransition = $lastTransition;
        $appointment->replacementAppointmentId = $replacementAppointmentId;
        $appointment->replacementSlot = $replacementSlot;

        return $appointment;
    }

    public function appointmentId(): string
    {
        return $this->appointmentId;
    }

    public function lastTransition(): ?AppointmentTransitionData
    {
        return $this->lastTransition;
    }

    public function replacementAppointmentId(): ?string
    {
        return $this->replacementAppointmentId;
    }

    public function replacementSlot(): ?AppointmentSlot
    {
        return $this->replacementSlot;
    }

    /**
     * @return list<AppointmentDomainEvent>
     */
    public function releaseRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function schedule(DateTimeImmutable $occurredAt, AppointmentActor $actor): void
    {
        AppointmentTransitionRules::assertCanSchedule($this->status);
        $this->applyTransition(AppointmentStatus::SCHEDULED, AppointmentEventType::SCHEDULED, $occurredAt, $actor);
    }

    public function confirm(DateTimeImmutable $occurredAt, AppointmentActor $actor): void
    {
        AppointmentTransitionRules::assertCanConfirm($this->status, $this->scheduledSlot, $occurredAt);
        $this->applyTransition(AppointmentStatus::CONFIRMED, AppointmentEventType::CONFIRMED, $occurredAt, $actor);
    }

    public function checkIn(DateTimeImmutable $occurredAt, AppointmentActor $actor, bool $adminOverride = false): void
    {
        AppointmentTransitionRules::assertCanCheckIn($this->status, $adminOverride);
        $this->applyTransition(
            AppointmentStatus::CHECKED_IN,
            AppointmentEventType::CHECKED_IN,
            $occurredAt,
            $actor,
            adminOverride: $adminOverride,
        );
    }

    public function start(DateTimeImmutable $occurredAt, AppointmentActor $actor): void
    {
        AppointmentTransitionRules::assertCanStart($this->status);
        $this->applyTransition(AppointmentStatus::IN_PROGRESS, AppointmentEventType::STARTED, $occurredAt, $actor);
    }

    public function complete(DateTimeImmutable $occurredAt, AppointmentActor $actor): void
    {
        AppointmentTransitionRules::assertCanComplete($this->status);
        $this->applyTransition(AppointmentStatus::COMPLETED, AppointmentEventType::COMPLETED, $occurredAt, $actor);
    }

    public function cancel(DateTimeImmutable $occurredAt, AppointmentActor $actor, string $reason): void
    {
        AppointmentTransitionRules::assertCanCancel($this->status, $reason);
        $this->applyTransition(AppointmentStatus::CANCELED, AppointmentEventType::CANCELED, $occurredAt, $actor, $reason);
    }

    public function markNoShow(DateTimeImmutable $occurredAt, AppointmentActor $actor, string $reason): void
    {
        AppointmentTransitionRules::assertCanMarkNoShow($this->status, $this->scheduledSlot, $occurredAt, $reason);
        $this->applyTransition(AppointmentStatus::NO_SHOW, AppointmentEventType::NO_SHOW, $occurredAt, $actor, $reason);
    }

    public function reschedule(
        AppointmentSlot $replacementSlot,
        DateTimeImmutable $occurredAt,
        AppointmentActor $actor,
        string $reason,
        ?string $replacementAppointmentId = null,
    ): void {
        AppointmentTransitionRules::assertCanReschedule($this->status, $reason, $replacementSlot);
        $this->replacementSlot = $replacementSlot;
        $this->replacementAppointmentId = $replacementAppointmentId;
        $this->applyTransition(
            AppointmentStatus::RESCHEDULED,
            AppointmentEventType::RESCHEDULED,
            $occurredAt,
            $actor,
            $reason,
            replacementAppointmentId: $replacementAppointmentId,
            replacementSlot: $replacementSlot,
        );
    }

    public function restore(DateTimeImmutable $occurredAt, AppointmentActor $actor): void
    {
        $restoredFromStatus = $this->status;
        AppointmentTransitionRules::assertCanRestore($restoredFromStatus, $this->scheduledSlot, $occurredAt);
        $this->replacementSlot = null;
        $this->replacementAppointmentId = null;
        $this->applyTransition(
            AppointmentStatus::SCHEDULED,
            AppointmentEventType::RESTORED,
            $occurredAt,
            $actor,
            restoredFromStatus: $restoredFromStatus,
        );
    }

    /**
     * @return array{
     *     appointment_id: string,
     *     tenant_id: string,
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: string|null,
     *     room_id: string|null,
     *     status: string,
     *     scheduled_slot: array{start_at: string, end_at: string, timezone: string},
     *     last_transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null,
     *         admin_override: bool,
     *         restored_from_status: string|null,
     *         replacement_appointment_id: string|null,
     *         replacement_slot: array{start_at: string, end_at: string, timezone: string}|null
     *     }|null,
     *     replacement_appointment_id: string|null,
     *     replacement_slot: array{start_at: string, end_at: string, timezone: string}|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'tenant_id' => $this->tenantId,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'room_id' => $this->roomId,
            'status' => $this->status->value,
            'scheduled_slot' => $this->scheduledSlot->toArray(),
            'last_transition' => $this->lastTransition?->toArray(),
            'replacement_appointment_id' => $this->replacementAppointmentId,
            'replacement_slot' => $this->replacementSlot?->toArray(),
        ];
    }

    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    private function applyTransition(
        AppointmentStatus $toStatus,
        AppointmentEventType $eventType,
        DateTimeImmutable $occurredAt,
        AppointmentActor $actor,
        ?string $reason = null,
        bool $adminOverride = false,
        ?AppointmentStatus $restoredFromStatus = null,
        ?string $replacementAppointmentId = null,
        ?AppointmentSlot $replacementSlot = null,
    ): void {
        $transition = new AppointmentTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason !== null ? trim($reason) : null,
            adminOverride: $adminOverride,
            restoredFromStatus: $restoredFromStatus,
            replacementAppointmentId: $replacementAppointmentId,
            replacementSlot: $replacementSlot,
        );

        $this->status = $toStatus;
        $this->lastTransition = $transition;
        $this->recordedEvents[] = new AppointmentDomainEvent(
            type: $eventType,
            appointmentId: $this->appointmentId,
            tenantId: $this->tenantId,
            patientId: $this->patientId,
            providerId: $this->providerId,
            clinicId: $this->clinicId,
            roomId: $this->roomId,
            status: $this->status,
            transition: $transition,
            scheduledSlot: $this->scheduledSlot,
        );
    }
}
