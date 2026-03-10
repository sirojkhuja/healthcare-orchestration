<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Scheduling\Application\Contracts\AppointmentRecurrenceRepository;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceData;
use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceMaterializationData;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use App\Modules\Scheduling\Domain\Appointments\InvalidAppointmentTransition;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentRecurrenceService
{
    private const STATUS_ACTIVE = 'active';

    private const STATUS_CANCELED = 'canceled';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentRecurrenceRepository $appointmentRecurrenceRepository,
        private readonly AppointmentAggregateMapper $appointmentAggregateMapper,
        private readonly AppointmentActorContext $appointmentActorContext,
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly ScheduledAppointmentCreator $scheduledAppointmentCreator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $appointmentId, array $attributes): AppointmentRecurrenceMaterializationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $sourceAppointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $this->assertSourceStatus($sourceAppointment);
        $normalized = $this->normalizeCreateAttributes($sourceAppointment, $attributes);
        $futureAppointments = $this->buildFutureAppointments($sourceAppointment, $normalized);
        foreach ($futureAppointments as $futureAppointment) {
            $this->assertSlotAvailable(
                providerId: $sourceAppointment->providerId,
                startAt: $futureAppointment['scheduled_start_at'],
                endAt: $futureAppointment['scheduled_end_at'],
                timezone: $sourceAppointment->timezone,
            );
        }
        $actor = $this->appointmentActorContext->current();
        $occurredAt = CarbonImmutable::now();
        /** @var AppointmentRecurrenceMaterializationData $result */
        $result = DB::transaction(function () use (
            $tenantId,
            $sourceAppointment,
            $normalized,
            $futureAppointments,
            $actor,
            $occurredAt,
        ): AppointmentRecurrenceMaterializationData {
            $recurrence = $this->appointmentRecurrenceRepository->create($tenantId, [
                'source_appointment_id' => $sourceAppointment->appointmentId,
                'patient_id' => $sourceAppointment->patientId,
                'provider_id' => $sourceAppointment->providerId,
                'clinic_id' => $sourceAppointment->clinicId,
                'room_id' => $sourceAppointment->roomId,
                'frequency' => $normalized['frequency'],
                'interval' => $normalized['interval'],
                'occurrence_count' => $normalized['occurrence_count'],
                'until_date' => $normalized['until_date']?->toDateString(),
                'timezone' => $sourceAppointment->timezone,
                'status' => self::STATUS_ACTIVE,
                'canceled_reason' => null,
            ]);
            $appointments = [];
            foreach ($futureAppointments as $futureAppointment) {
                $appointments[] = $this->scheduledAppointmentCreator->create(
                    tenantId: $tenantId,
                    patientId: $sourceAppointment->patientId,
                    providerId: $sourceAppointment->providerId,
                    clinicId: $sourceAppointment->clinicId,
                    roomId: $sourceAppointment->roomId,
                    scheduledStartAt: $futureAppointment['scheduled_start_at'],
                    scheduledEndAt: $futureAppointment['scheduled_end_at'],
                    timezone: $sourceAppointment->timezone,
                    actor: $actor,
                    occurredAt: $occurredAt,
                    recurrenceId: $recurrence->recurrenceId,
                );
            }
            $materialized = new AppointmentRecurrenceMaterializationData($recurrence, $appointments);
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.recurrence_created',
                objectType: 'appointment_recurrence',
                objectId: $recurrence->recurrenceId,
                after: $materialized->toArray(),
                metadata: [
                    'source_appointment_id' => $sourceAppointment->appointmentId,
                    'generated_count' => count($appointments),
                ],
            ));

            return $materialized;
        });
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $result;
    }

    public function cancel(string $recurrenceId, string $reason): AppointmentRecurrenceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $recurrence = $this->recurrenceOrFail($tenantId, $recurrenceId);
        $this->assertActiveRecurrence($recurrence);
        $reason = $this->requiredReason($reason);
        $actor = $this->appointmentActorContext->current();
        $occurredAt = CarbonImmutable::now();
        /** @var AppointmentRecurrenceData $updatedRecurrence */
        $updatedRecurrence = DB::transaction(function () use ($tenantId, $recurrence, $reason, $actor, $occurredAt): AppointmentRecurrenceData {
            $canceledAppointmentIds = [];
            foreach ($this->appointmentRepository->listForRecurrence($tenantId, $recurrence->recurrenceId) as $appointment) {
                if (! $this->shouldCancelGeneratedAppointment($appointment, $occurredAt)) {
                    continue;
                }
                $aggregate = $this->appointmentAggregateMapper->fromData($appointment);
                try {
                    $aggregate->cancel($occurredAt->toDateTimeImmutable(), $actor, $reason);
                } catch (InvalidAppointmentTransition $exception) {
                    throw new ConflictHttpException($exception->getMessage(), $exception);
                }
                $updatedAppointment = $this->persistAppointment($tenantId, $appointment, $aggregate->snapshot()['status'], $aggregate->snapshot()['last_transition']);
                $canceledAppointmentIds[] = $updatedAppointment->appointmentId;
                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'appointments.canceled',
                    objectType: 'appointment',
                    objectId: $updatedAppointment->appointmentId,
                    before: $appointment->toArray(),
                    after: $updatedAppointment->toArray(),
                    metadata: [
                        'source' => 'recurrence_cancel',
                        'recurrence_id' => $recurrence->recurrenceId,
                    ],
                ));
            }
            $updatedRecurrence = $this->appointmentRecurrenceRepository->update($tenantId, $recurrence->recurrenceId, [
                'status' => self::STATUS_CANCELED,
                'canceled_reason' => $reason,
            ]);
            if (! $updatedRecurrence instanceof AppointmentRecurrenceData) {
                throw new LogicException('Canceled recurrence could not be reloaded.');
            }
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.recurrence_canceled',
                objectType: 'appointment_recurrence',
                objectId: $updatedRecurrence->recurrenceId,
                before: $recurrence->toArray(),
                after: [
                    ...$updatedRecurrence->toArray(),
                    'canceled_appointment_ids' => $canceledAppointmentIds,
                ],
            ));

            return $updatedRecurrence;
        });
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $updatedRecurrence;
    }

    private function advanceSlot(CarbonImmutable $value, string $frequency, int $interval, int $step): CarbonImmutable
    {
        $distance = $interval * $step;

        return match ($frequency) {
            'daily' => $value->addDays($distance),
            'weekly' => $value->addWeeks($distance),
            'monthly' => $value->addMonthsNoOverflow($distance),
            default => throw new UnprocessableEntityHttpException('Appointment recurrence frequency is invalid.'),
        };
    }

    private function appointmentOrFail(string $tenantId, string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    private function assertActiveRecurrence(AppointmentRecurrenceData $recurrence): void
    {
        if ($recurrence->status !== self::STATUS_ACTIVE) {
            throw new ConflictHttpException('Only active appointment recurrences may be canceled.');
        }
    }

    /**
     * @param  list<array{scheduled_start_at: CarbonImmutable, scheduled_end_at: CarbonImmutable}>  $appointments
     */
    private function assertFutureAppointmentsExist(array $appointments): void
    {
        if ($appointments === []) {
            throw new UnprocessableEntityHttpException('The requested recurrence must generate at least one future appointment.');
        }
    }

    private function assertSlotAvailable(
        string $providerId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $timezone,
    ): void {
        if (! $this->availabilitySlotService->isSlotAvailable($providerId, $startAt, $endAt, $timezone)) {
            throw new ConflictHttpException('The requested appointment slot is not currently available.');
        }
    }

    private function assertSourceStatus(AppointmentData $appointment): void
    {
        if (! in_array($appointment->status, [AppointmentStatus::SCHEDULED->value, AppointmentStatus::CONFIRMED->value], true)) {
            throw new ConflictHttpException('Only scheduled or confirmed appointments may be made recurring.');
        }
    }

    /**
     * @param  array{
     *      frequency: string,
     *      interval: int,
     *      occurrence_count: ?int,
     *      until_date: ?CarbonImmutable
     *  }  $normalized
     * @return list<array{scheduled_start_at: CarbonImmutable, scheduled_end_at: CarbonImmutable}>
     */
    private function buildFutureAppointments(AppointmentData $sourceAppointment, array $normalized): array
    {
        $appointments = [];
        if ($normalized['occurrence_count'] !== null) {
            for ($step = 1; $step < $normalized['occurrence_count']; $step++) {
                $appointments[] = [
                    'scheduled_start_at' => $this->advanceSlot($sourceAppointment->scheduledStartAt, $normalized['frequency'], $normalized['interval'], $step),
                    'scheduled_end_at' => $this->advanceSlot($sourceAppointment->scheduledEndAt, $normalized['frequency'], $normalized['interval'], $step),
                ];
            }
            $this->assertFutureAppointmentsExist($appointments);

            return $appointments;
        }
        $untilDate = $normalized['until_date'] ?? throw new LogicException('Recurrence until_date must exist when occurrence_count is absent.');
        for ($step = 1; ; $step++) {
            $scheduledStartAt = $this->advanceSlot($sourceAppointment->scheduledStartAt, $normalized['frequency'], $normalized['interval'], $step);
            if ($scheduledStartAt->toDateString() > $untilDate->toDateString()) {
                break;
            }
            $appointments[] = [
                'scheduled_start_at' => $scheduledStartAt,
                'scheduled_end_at' => $this->advanceSlot($sourceAppointment->scheduledEndAt, $normalized['frequency'], $normalized['interval'], $step),
            ];
        }
        $this->assertFutureAppointmentsExist($appointments);

        return $appointments;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *      frequency: string,
     *      interval: int,
     *      occurrence_count: ?int,
     *      until_date: ?CarbonImmutable
     *  }
     */
    private function normalizeCreateAttributes(AppointmentData $sourceAppointment, array $attributes): array
    {
        $frequency = $this->requiredString($attributes['frequency'] ?? null, 'Appointment recurrence frequency is required.');

        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw new UnprocessableEntityHttpException('Appointment recurrence frequency must be one of daily, weekly, or monthly.');
        }
        $interval = filter_var($attributes['interval'] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($interval) || $interval < 1 || $interval > 12) {
            throw new UnprocessableEntityHttpException('Appointment recurrence interval must be between 1 and 12.');
        }
        $hasOccurrenceCount = array_key_exists('occurrence_count', $attributes) && $attributes['occurrence_count'] !== null;
        $hasUntilDate = array_key_exists('until_date', $attributes) && $attributes['until_date'] !== null;
        if ($hasOccurrenceCount === $hasUntilDate) {
            throw new UnprocessableEntityHttpException('Appointment recurrence requests require exactly one of occurrence_count or until_date.');
        }

        if ($hasOccurrenceCount) {
            $occurrenceCount = filter_var($attributes['occurrence_count'], FILTER_VALIDATE_INT);

            if (! is_int($occurrenceCount) || $occurrenceCount < 2 || $occurrenceCount > 24) {
                throw new UnprocessableEntityHttpException('Appointment recurrence occurrence_count must be between 2 and 24.');
            }

            return [
                'frequency' => $frequency,
                'interval' => $interval,
                'occurrence_count' => $occurrenceCount,
                'until_date' => null,
            ];
        }

        $sourceDate = $sourceAppointment->scheduledStartAt->startOfDay();
        $untilDate = (CarbonImmutable::createFromFormat('Y-m-d', $this->requiredString($attributes['until_date'], 'Appointment recurrence until_date is required.'), $sourceAppointment->timezone)
            ?: throw new UnprocessableEntityHttpException('Appointment recurrence until_date must use Y-m-d format.'))
            ->startOfDay();

        if ($untilDate->lessThanOrEqualTo($sourceDate)) {
            throw new UnprocessableEntityHttpException('Appointment recurrence until_date must be after the source appointment date.');
        }

        if ($untilDate->greaterThan($sourceDate->addDays(180))) {
            throw new UnprocessableEntityHttpException('Appointment recurrence until_date may not be more than 180 days after the source appointment date.');
        }

        return [
            'frequency' => $frequency,
            'interval' => $interval,
            'occurrence_count' => null,
            'until_date' => $untilDate,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    private function persistAppointment(string $tenantId, AppointmentData $before, string $status, ?array $lastTransition): AppointmentData
    {
        $updated = $this->appointmentRepository->update($tenantId, $before->appointmentId, [
            'status' => $status,
            'last_transition' => $lastTransition,
        ]);

        if (! $updated instanceof AppointmentData) {
            throw new LogicException('Updated appointment could not be reloaded.');
        }

        return $updated;
    }

    private function recurrenceOrFail(string $tenantId, string $recurrenceId): AppointmentRecurrenceData
    {
        $recurrence = $this->appointmentRecurrenceRepository->findInTenant($tenantId, $recurrenceId);

        if (! $recurrence instanceof AppointmentRecurrenceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $recurrence;
    }

    private function requiredReason(string $reason): string
    {
        return $this->requiredString($reason, 'Appointment recurrence cancellation requires a non-empty reason.');
    }

    private function requiredString(mixed $value, string $message): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException($message);
        }

        return trim($value);
    }

    private function shouldCancelGeneratedAppointment(AppointmentData $appointment, CarbonImmutable $occurredAt): bool
    {
        return in_array($appointment->status, [AppointmentStatus::SCHEDULED->value, AppointmentStatus::CONFIRMED->value], true)
            && $appointment->scheduledStartAt->greaterThan($occurredAt);
    }
}
