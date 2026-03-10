<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\BulkAppointmentUpdateData;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentBulkUpdateService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentAttributeNormalizer $appointmentAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>  $appointmentIds
     * @param  array{
     *     patient_id?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     scheduled_start_at?: string,
     *     scheduled_end_at?: string,
     *     timezone?: string
     * }  $changes
     */
    public function update(array $appointmentIds, array $changes): BulkAppointmentUpdateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedIds = $this->appointmentIds($appointmentIds);
        $normalizedChanges = $this->sanitizeChanges($changes);
        $operationId = (string) Str::uuid();
        $updatedAppointments = [];
        $mutations = [];
        $updatedFields = array_keys($normalizedChanges);

        /** @var list<AppointmentData> $updatedAppointments */
        $updatedAppointments = DB::transaction(function () use (
            $tenantId,
            $normalizedIds,
            $normalizedChanges,
            &$mutations,
        ): array {
            $appointments = [];

            foreach ($normalizedIds as $appointmentId) {
                $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
                $this->assertDraft($appointment);
                $updates = $this->appointmentAttributeNormalizer->normalizePatch($appointment, $normalizedChanges);

                if ($updates === []) {
                    $appointments[] = $appointment;

                    continue;
                }

                $updated = $this->appointmentRepository->update($tenantId, $appointment->appointmentId, $updates);

                if (! $updated instanceof AppointmentData) {
                    throw new \LogicException('Updated appointment could not be reloaded.');
                }

                $appointments[] = $updated;
                $mutations[] = [
                    'before' => $appointment,
                    'after' => $updated,
                ];
            }

            return $appointments;
        });

        if ($mutations !== []) {
            $this->recordAudit($operationId, $updatedFields, $updatedAppointments, $mutations);
        }

        return new BulkAppointmentUpdateData(
            operationId: $operationId,
            affectedCount: count($updatedAppointments),
            updatedFields: $updatedFields,
            appointments: $updatedAppointments,
        );
    }

    /**
     * @param  list<string>  $updatedFields
     * @param  list<AppointmentData>  $appointments
     * @param  list<array{before: AppointmentData, after: AppointmentData}>  $mutations
     */
    private function recordAudit(string $operationId, array $updatedFields, array $appointments, array $mutations): void
    {
        $appointmentIds = array_map(
            static fn (AppointmentData $appointment): string => $appointment->appointmentId,
            $appointments,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.bulk_updated',
            objectType: 'appointment_bulk_operation',
            objectId: $operationId,
            after: [
                'operation_id' => $operationId,
                'appointment_ids' => $appointmentIds,
                'updated_fields' => $updatedFields,
                'affected_count' => count($appointments),
            ],
            metadata: [
                'appointment_ids' => $appointmentIds,
                'updated_fields' => $updatedFields,
            ],
        ));

        foreach ($mutations as $mutation) {
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.updated',
                objectType: 'appointment',
                objectId: $mutation['after']->appointmentId,
                before: $mutation['before']->toArray(),
                after: $mutation['after']->toArray(),
                metadata: [
                    'source' => 'bulk_update',
                    'bulk_operation_id' => $operationId,
                ],
            ));
        }
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return list<string>
     */
    private function appointmentIds(array $appointmentIds): array
    {
        $normalized = array_values(array_filter(
            $appointmentIds,
            static fn (string $appointmentId): bool => $appointmentId !== '',
        ));

        if ($normalized === []) {
            throw new UnprocessableEntityHttpException('Bulk appointment updates require at least one appointment id.');
        }

        if (count($normalized) > 100) {
            throw new UnprocessableEntityHttpException('Bulk appointment updates may target at most 100 appointments.');
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new UnprocessableEntityHttpException('Bulk appointment updates require distinct appointment ids.');
        }

        return $normalized;
    }

    private function appointmentOrFail(string $tenantId, string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    /**
     * @param  array{
     *     patient_id?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     scheduled_start_at?: string,
     *     scheduled_end_at?: string,
     *     timezone?: string
     * }  $changes
     * @return array{
     *     patient_id?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     scheduled_start_at?: string,
     *     scheduled_end_at?: string,
     *     timezone?: string
     * }
     */
    private function sanitizeChanges(array $changes): array
    {
        $normalized = [];

        if (array_key_exists('patient_id', $changes)) {
            $normalized['patient_id'] = $changes['patient_id'];
        }

        if (array_key_exists('provider_id', $changes)) {
            $normalized['provider_id'] = $changes['provider_id'];
        }

        if (array_key_exists('clinic_id', $changes)) {
            $normalized['clinic_id'] = $changes['clinic_id'];
        }

        if (array_key_exists('room_id', $changes)) {
            $normalized['room_id'] = $changes['room_id'];
        }

        if (array_key_exists('scheduled_start_at', $changes)) {
            $normalized['scheduled_start_at'] = $changes['scheduled_start_at'];
        }

        if (array_key_exists('scheduled_end_at', $changes)) {
            $normalized['scheduled_end_at'] = $changes['scheduled_end_at'];
        }

        if (array_key_exists('timezone', $changes)) {
            $normalized['timezone'] = $changes['timezone'];
        }

        if ($normalized === []) {
            throw new UnprocessableEntityHttpException('Bulk appointment updates require at least one change.');
        }

        return $normalized;
    }

    private function assertDraft(AppointmentData $appointment): void
    {
        if ($appointment->status !== AppointmentStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft appointments may be updated through the bulk route.');
        }
    }
}
