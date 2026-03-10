<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentRescheduleData;
use App\Modules\Scheduling\Application\Data\BulkAppointmentTransitionData;
use App\Modules\Scheduling\Domain\Appointments\Appointment;
use App\Modules\Scheduling\Domain\Appointments\AppointmentActor;
use App\Modules\Scheduling\Domain\Appointments\AppointmentSlot;
use App\Modules\Scheduling\Domain\Appointments\InvalidAppointmentTransition;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentAttributeNormalizer $appointmentAttributeNormalizer,
        private readonly AppointmentAggregateMapper $appointmentAggregateMapper,
        private readonly AppointmentActorContext $appointmentActorContext,
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly ScheduledAppointmentCreator $scheduledAppointmentCreator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function cancel(string $appointmentId, string $reason): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.canceled',
            mutator: function (Appointment $appointment, CarbonImmutable $occurredAt, AppointmentActor $actor) use ($reason): void {
                $appointment->cancel($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    /**
     * @param  list<string>  $appointmentIds
     */
    public function bulkCancel(array $appointmentIds, string $reason): BulkAppointmentTransitionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedIds = $this->normalizedBulkIds($appointmentIds);
        $actor = $this->currentActor();
        $occurredAt = CarbonImmutable::now();
        $updatedAppointments = [];
        $operationId = (string) Str::uuid();

        /** @var list<AppointmentData> $updatedAppointments */
        $updatedAppointments = DB::transaction(function () use (
            $tenantId,
            $normalizedIds,
            $reason,
            $actor,
            $occurredAt,
        ): array {
            $appointments = [];

            foreach ($normalizedIds as $appointmentId) {
                $before = $this->appointmentOrFail($appointmentId);
                $aggregate = $this->aggregateFromData($before);

                try {
                    $aggregate->cancel($occurredAt->toDateTimeImmutable(), $actor, $reason);
                } catch (InvalidAppointmentTransition $exception) {
                    throw new ConflictHttpException($exception->getMessage(), $exception);
                }

                $updated = $this->persistAggregate($tenantId, $before, $aggregate);
                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'appointments.canceled',
                    objectType: 'appointment',
                    objectId: $updated->appointmentId,
                    before: $before->toArray(),
                    after: $updated->toArray(),
                    metadata: [
                        'source' => 'bulk_cancel',
                    ],
                ));
                $appointments[] = $updated;
            }

            return $appointments;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.bulk_canceled',
            objectType: 'appointment_bulk_operation',
            objectId: $operationId,
            after: [
                'operation_id' => $operationId,
                'appointment_ids' => $normalizedIds,
                'affected_count' => count($updatedAppointments),
            ],
        ));
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return new BulkAppointmentTransitionData(
            operationId: $operationId,
            affectedCount: count($updatedAppointments),
            appointments: $updatedAppointments,
        );
    }

    public function checkIn(string $appointmentId, bool $adminOverride = false): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.checked_in',
            mutator: function (Appointment $appointment, CarbonImmutable $occurredAt, AppointmentActor $actor) use ($adminOverride): void {
                $appointment->checkIn($occurredAt->toDateTimeImmutable(), $actor, $adminOverride);
            },
        );
    }

    public function complete(string $appointmentId): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.completed',
            mutator: function (Appointment $appointment, CarbonImmutable $occurredAt, AppointmentActor $actor): void {
                $appointment->complete($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function confirm(string $appointmentId): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.confirmed',
            mutator: function (Appointment $appointment, CarbonImmutable $occurredAt, AppointmentActor $actor): void {
                $appointment->confirm($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function markNoShow(string $appointmentId, string $reason): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.no_show',
            mutator: function (Appointment $appointment, CarbonImmutable $occurredAt, AppointmentActor $actor) use ($reason): void {
                $appointment->markNoShow($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function bulkReschedule(array $items): BulkAppointmentTransitionData
    {
        if ($items === [] || count($items) > 100) {
            throw new UnprocessableEntityHttpException('Bulk reschedule requests must include between 1 and 100 items.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $actor = $this->currentActor();
        $occurredAt = CarbonImmutable::now();
        $preparedItems = [];
        $operationId = (string) Str::uuid();
        /** @var list<string> $sourceIds */
        $sourceIds = [];

        foreach ($items as $item) {
            /** @var mixed $appointmentIdValue */
            $appointmentIdValue = $item['appointment_id'] ?? null;
            $appointmentId = is_string($appointmentIdValue) ? $appointmentIdValue : null;

            if ($appointmentId === null || $appointmentId === '') {
                throw new UnprocessableEntityHttpException('Bulk reschedule items require appointment_id.');
            }

            if (in_array($appointmentId, $sourceIds, true)) {
                throw new UnprocessableEntityHttpException('Bulk reschedule items require distinct appointment ids.');
            }

            $sourceIds[] = $appointmentId;
            $before = $this->appointmentOrFail($appointmentId);
            $replacement = $this->normalizedReplacementAttributes($before, $item, $tenantId);
            $this->assertNotSameSlot($before, $replacement);

            $preparedItems[] = [
                'before' => $before,
                'reason' => $this->requiredReason($item['reason'] ?? null),
                'replacement' => $replacement,
            ];
        }

        $this->assertNoBulkReplacementOverlap($preparedItems);

        foreach ($preparedItems as $preparedItem) {
            $this->assertSlotAvailable(
                providerId: $preparedItem['before']->providerId,
                startAt: $preparedItem['replacement']['scheduled_start_at'],
                endAt: $preparedItem['replacement']['scheduled_end_at'],
                timezone: $preparedItem['replacement']['timezone'],
                excludedAppointmentIds: $sourceIds,
            );
        }

        $updatedAppointments = [];
        $replacementAppointments = [];

        /** @var array{updated: list<AppointmentData>, replacement: list<AppointmentData>} $results */
        $results = DB::transaction(function () use (
            $tenantId,
            $preparedItems,
            $actor,
            $occurredAt,
        ): array {
            $updated = [];
            $replacementAppointments = [];

            foreach ($preparedItems as $preparedItem) {
                $replacementAppointment = $this->createScheduledAppointment($tenantId, $preparedItem['replacement'], $actor, $occurredAt);
                $aggregate = $this->aggregateFromData($preparedItem['before']);

                try {
                    $aggregate->reschedule(
                        replacementSlot: new AppointmentSlot(
                            $preparedItem['replacement']['scheduled_start_at']->toDateTimeImmutable(),
                            $preparedItem['replacement']['scheduled_end_at']->toDateTimeImmutable(),
                            $preparedItem['replacement']['timezone'],
                        ),
                        occurredAt: $occurredAt->toDateTimeImmutable(),
                        actor: $actor,
                        reason: $preparedItem['reason'],
                        replacementAppointmentId: $replacementAppointment->appointmentId,
                    );
                } catch (InvalidAppointmentTransition $exception) {
                    throw new ConflictHttpException($exception->getMessage(), $exception);
                }

                $updatedAppointment = $this->persistAggregate($tenantId, $preparedItem['before'], $aggregate);
                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'appointments.rescheduled',
                    objectType: 'appointment',
                    objectId: $updatedAppointment->appointmentId,
                    before: $preparedItem['before']->toArray(),
                    after: $updatedAppointment->toArray(),
                    metadata: [
                        'source' => 'bulk_reschedule',
                        'replacement_appointment_id' => $replacementAppointment->appointmentId,
                    ],
                ));
                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'appointments.replacement_created',
                    objectType: 'appointment',
                    objectId: $replacementAppointment->appointmentId,
                    after: $replacementAppointment->toArray(),
                    metadata: [
                        'source' => 'bulk_reschedule',
                        'source_appointment_id' => $updatedAppointment->appointmentId,
                    ],
                ));

                $updated[] = $updatedAppointment;
                $replacementAppointments[] = $replacementAppointment;
            }

            return [
                'updated' => $updated,
                'replacement' => $replacementAppointments,
            ];
        });

        $updatedAppointments = $results['updated'];
        $replacementAppointments = $results['replacement'];
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.bulk_rescheduled',
            objectType: 'appointment_bulk_operation',
            objectId: $operationId,
            after: [
                'operation_id' => $operationId,
                'affected_count' => count($updatedAppointments),
                'appointment_ids' => array_map(
                    static fn (AppointmentData $appointment): string => $appointment->appointmentId,
                    $updatedAppointments,
                ),
            ],
        ));
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return new BulkAppointmentTransitionData(
            operationId: $operationId,
            affectedCount: count($updatedAppointments),
            appointments: $updatedAppointments,
            replacementAppointments: $replacementAppointments,
        );
    }

    public function restore(string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentOrFail($appointmentId);
        $this->assertSlotAvailable(
            providerId: $appointment->providerId,
            startAt: $appointment->scheduledStartAt,
            endAt: $appointment->scheduledEndAt,
            timezone: $appointment->timezone,
        );

        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.restored',
            mutator: function (Appointment $aggregate, CarbonImmutable $occurredAt, AppointmentActor $actor): void {
                $aggregate->restore($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reschedule(string $appointmentId, array $attributes): AppointmentRescheduleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->appointmentOrFail($appointmentId);
        $replacementAttributes = $this->normalizedReplacementAttributes($before, $attributes, $tenantId);
        $this->assertNotSameSlot($before, $replacementAttributes);
        $this->assertSlotAvailable(
            providerId: $before->providerId,
            startAt: $replacementAttributes['scheduled_start_at'],
            endAt: $replacementAttributes['scheduled_end_at'],
            timezone: $replacementAttributes['timezone'],
            excludedAppointmentIds: [$before->appointmentId],
        );
        $reason = $this->requiredReason($attributes['reason'] ?? null);
        $actor = $this->currentActor();
        $occurredAt = CarbonImmutable::now();

        /** @var AppointmentRescheduleData $result */
        $result = DB::transaction(function () use (
            $tenantId,
            $before,
            $replacementAttributes,
            $reason,
            $actor,
            $occurredAt,
        ): AppointmentRescheduleData {
            $replacement = $this->createScheduledAppointment($tenantId, $replacementAttributes, $actor, $occurredAt);
            $aggregate = $this->aggregateFromData($before);

            try {
                $aggregate->reschedule(
                    replacementSlot: new AppointmentSlot(
                        $replacementAttributes['scheduled_start_at']->toDateTimeImmutable(),
                        $replacementAttributes['scheduled_end_at']->toDateTimeImmutable(),
                        $replacementAttributes['timezone'],
                    ),
                    occurredAt: $occurredAt->toDateTimeImmutable(),
                    actor: $actor,
                    reason: $reason,
                    replacementAppointmentId: $replacement->appointmentId,
                );
            } catch (InvalidAppointmentTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $updated = $this->persistAggregate($tenantId, $before, $aggregate);

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.rescheduled',
                objectType: 'appointment',
                objectId: $updated->appointmentId,
                before: $before->toArray(),
                after: $updated->toArray(),
                metadata: [
                    'replacement_appointment_id' => $replacement->appointmentId,
                ],
            ));
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.replacement_created',
                objectType: 'appointment',
                objectId: $replacement->appointmentId,
                after: $replacement->toArray(),
                metadata: [
                    'source_appointment_id' => $before->appointmentId,
                ],
            ));

            return new AppointmentRescheduleData($updated, $replacement);
        });

        $this->availabilitySlotService->rebuild(
            providerId: $before->providerId,
            dateFrom: $before->scheduledStartAt->toDateString(),
            dateTo: $replacementAttributes['scheduled_start_at']->toDateString(),
            limit: 1000,
        );
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $result;
    }

    public function schedule(string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentOrFail($appointmentId);
        $this->assertSlotAvailable(
            providerId: $appointment->providerId,
            startAt: $appointment->scheduledStartAt,
            endAt: $appointment->scheduledEndAt,
            timezone: $appointment->timezone,
        );

        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.scheduled',
            mutator: function (Appointment $aggregate, CarbonImmutable $occurredAt, AppointmentActor $actor): void {
                $aggregate->schedule($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function start(string $appointmentId): AppointmentData
    {
        return $this->transition(
            appointmentId: $appointmentId,
            auditAction: 'appointments.started',
            mutator: function (Appointment $aggregate, CarbonImmutable $occurredAt, AppointmentActor $actor): void {
                $aggregate->start($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    /**
     * @param  callable(Appointment, CarbonImmutable, AppointmentActor): void  $mutator
     */
    private function transition(string $appointmentId, string $auditAction, callable $mutator): AppointmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->appointmentOrFail($appointmentId);
        $actor = $this->currentActor();
        $occurredAt = CarbonImmutable::now();

        /** @var AppointmentData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): AppointmentData {
            $aggregate = $this->aggregateFromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidAppointmentTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            return $this->persistAggregate($tenantId, $before, $aggregate);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'appointment',
            objectId: $updated->appointmentId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $updated;
    }

    private function aggregateFromData(AppointmentData $appointment): Appointment
    {
        return $this->appointmentAggregateMapper->fromData($appointment);
    }

    private function appointmentOrFail(string $appointmentId): AppointmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string
     * }  $attributes
     */
    private function createScheduledAppointment(
        string $tenantId,
        array $attributes,
        AppointmentActor $actor,
        CarbonImmutable $occurredAt,
        ?string $recurrenceId = null,
    ): AppointmentData {
        return $this->scheduledAppointmentCreator->create(
            tenantId: $tenantId,
            patientId: $attributes['patient_id'],
            providerId: $attributes['provider_id'],
            clinicId: $attributes['clinic_id'],
            roomId: $attributes['room_id'],
            scheduledStartAt: $attributes['scheduled_start_at'],
            scheduledEndAt: $attributes['scheduled_end_at'],
            timezone: $attributes['timezone'],
            actor: $actor,
            occurredAt: $occurredAt,
            recurrenceId: $recurrenceId,
        );
    }

    private function currentActor(): AppointmentActor
    {
        return $this->appointmentActorContext->current();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string
     * }
     */
    private function normalizedReplacementAttributes(AppointmentData $appointment, array $attributes, string $tenantId): array
    {
        return $this->appointmentAttributeNormalizer->normalizeCreate([
            'patient_id' => $appointment->patientId,
            'provider_id' => $appointment->providerId,
            'clinic_id' => $attributes['clinic_id'] ?? $appointment->clinicId,
            'room_id' => array_key_exists('room_id', $attributes) ? $attributes['room_id'] : $appointment->roomId,
            'scheduled_start_at' => $attributes['replacement_start_at'] ?? null,
            'scheduled_end_at' => $attributes['replacement_end_at'] ?? null,
            'timezone' => $attributes['timezone'] ?? null,
        ], $tenantId);
    }

    private function persistAggregate(string $tenantId, AppointmentData $before, Appointment $aggregate): AppointmentData
    {
        $snapshot = $aggregate->snapshot();
        $updated = $this->appointmentRepository->update($tenantId, $before->appointmentId, [
            'status' => $snapshot['status'],
            'last_transition' => $snapshot['last_transition'],
        ]);

        if (! $updated instanceof AppointmentData) {
            throw new \LogicException('Updated appointment could not be reloaded.');
        }

        return $updated;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string
     * }  $replacement
     */
    private function assertNotSameSlot(AppointmentData $appointment, array $replacement): void
    {
        if (
            $appointment->clinicId === $replacement['clinic_id']
            && $appointment->roomId === $replacement['room_id']
            && $appointment->timezone === $replacement['timezone']
            && $appointment->scheduledStartAt->equalTo($replacement['scheduled_start_at'])
            && $appointment->scheduledEndAt->equalTo($replacement['scheduled_end_at'])
        ) {
            throw new UnprocessableEntityHttpException('Reschedule replacement slot must differ from the current appointment slot.');
        }
    }

    /**
     * @param  list<string>  $excludedAppointmentIds
     */
    private function assertSlotAvailable(
        string $providerId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $timezone,
        array $excludedAppointmentIds = [],
    ): void {
        if (! $this->availabilitySlotService->isSlotAvailable($providerId, $startAt, $endAt, $timezone, $excludedAppointmentIds)) {
            throw new ConflictHttpException('The requested appointment slot is not currently available.');
        }
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return list<string>
     */
    private function normalizedBulkIds(array $appointmentIds): array
    {
        $normalized = array_values(array_filter(
            $appointmentIds,
            static fn (string $appointmentId): bool => $appointmentId !== '',
        ));

        if ($normalized === [] || count($normalized) > 100) {
            throw new UnprocessableEntityHttpException('Bulk appointment workflow routes require between 1 and 100 appointment ids.');
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new UnprocessableEntityHttpException('Bulk appointment workflow routes require distinct appointment ids.');
        }

        return $normalized;
    }

    /**
     * @param  list<array{
     *     before: AppointmentData,
     *     reason: string,
     *     replacement: array{
     *         patient_id: string,
     *         provider_id: string,
     *         clinic_id: ?string,
     *         room_id: ?string,
     *         scheduled_start_at: CarbonImmutable,
     *         scheduled_end_at: CarbonImmutable,
     *         timezone: string
     *     }
     * }>  $preparedItems
     */
    private function assertNoBulkReplacementOverlap(array $preparedItems): void
    {
        $count = count($preparedItems);

        for ($index = 0; $index < $count; $index++) {
            for ($compareIndex = $index + 1; $compareIndex < $count; $compareIndex++) {
                $left = $preparedItems[$index];
                $right = $preparedItems[$compareIndex];

                if ($left['before']->providerId !== $right['before']->providerId) {
                    continue;
                }

                if (
                    $left['replacement']['scheduled_start_at']->lessThan($right['replacement']['scheduled_end_at'])
                    && $right['replacement']['scheduled_start_at']->lessThan($left['replacement']['scheduled_end_at'])
                ) {
                    throw new ConflictHttpException('Bulk reschedule replacement slots may not overlap for the same provider.');
                }
            }
        }
    }

    private function requiredReason(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException('Appointment workflow actions require a non-empty reason.');
        }

        return trim($value);
    }
}
