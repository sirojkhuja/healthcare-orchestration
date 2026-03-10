<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AppointmentAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentAttributeNormalizer $appointmentAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AppointmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->appointmentAttributeNormalizer->normalizeCreate($attributes, $tenantId);
        $appointment = $this->appointmentRepository->create($tenantId, [
            ...$normalized,
            'status' => AppointmentStatus::DRAFT->value,
            'last_transition' => null,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.created',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            after: $appointment->toArray(),
        ));

        return $appointment;
    }

    public function delete(string $appointmentId): AppointmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($appointmentId);
        $this->assertDraft($appointment, 'deleted');
        $deletedAt = CarbonImmutable::now();

        if (! $this->appointmentRepository->softDelete($tenantId, $appointment->appointmentId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->appointmentRepository->findInTenant($tenantId, $appointment->appointmentId, true);

        if (! $deleted instanceof AppointmentData) {
            throw new LogicException('Deleted appointment could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.deleted',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            before: $appointment->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $appointmentId): AppointmentData
    {
        return $this->appointmentOrFail($appointmentId);
    }

    /**
     * @return list<AppointmentData>
     */
    public function list(): array
    {
        return $this->appointmentRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $appointmentId, array $attributes): AppointmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($appointmentId);
        $this->assertDraft($appointment, 'updated');
        $updates = $this->appointmentAttributeNormalizer->normalizePatch($appointment, $attributes);

        if ($updates === []) {
            return $appointment;
        }

        $updated = $this->appointmentRepository->update($tenantId, $appointment->appointmentId, $updates);

        if (! $updated instanceof AppointmentData) {
            throw new LogicException('Updated appointment could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.updated',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            before: $appointment->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
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

    private function assertDraft(AppointmentData $appointment, string $action): void
    {
        if ($appointment->status !== AppointmentStatus::DRAFT->value) {
            throw new ConflictHttpException(sprintf(
                'Only draft appointments may be %s through the CRUD endpoint.',
                $action,
            ));
        }
    }
}
