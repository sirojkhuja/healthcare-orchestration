<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Pharmacy\Application\Contracts\PrescriptionRepository;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PrescriptionAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PrescriptionRepository $prescriptionRepository,
        private readonly PrescriptionAttributeNormalizer $prescriptionAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly PrescriptionOutboxPublisher $prescriptionOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PrescriptionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $prescription = $this->prescriptionRepository->create(
            $tenantId,
            $this->prescriptionAttributeNormalizer->normalizeCreate($attributes, $tenantId),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'prescriptions.created',
            objectType: 'prescription',
            objectId: $prescription->prescriptionId,
            after: $prescription->toArray(),
        ));
        $this->prescriptionOutboxPublisher->publishPrescriptionEvent('prescription.created', $prescription);

        return $prescription;
    }

    public function delete(string $prescriptionId): PrescriptionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $prescription = $this->prescriptionOrFail($prescriptionId);

        if (! in_array($prescription->status, [PrescriptionStatus::DRAFT->value, PrescriptionStatus::CANCELED->value], true)) {
            throw new ConflictHttpException('Only draft or canceled prescriptions may be deleted through the CRUD endpoint.');
        }

        $deletedAt = CarbonImmutable::now();

        if (! $this->prescriptionRepository->softDelete($tenantId, $prescriptionId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->prescriptionRepository->findInTenant($tenantId, $prescriptionId, true);

        if (! $deleted instanceof PrescriptionData) {
            throw new LogicException('Deleted prescription could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'prescriptions.deleted',
            objectType: 'prescription',
            objectId: $deleted->prescriptionId,
            before: $prescription->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $prescriptionId): PrescriptionData
    {
        return $this->prescriptionOrFail($prescriptionId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $prescriptionId, array $attributes): PrescriptionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $prescription = $this->prescriptionOrFail($prescriptionId);

        if ($prescription->status !== PrescriptionStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft prescriptions may be updated through the CRUD endpoint.');
        }

        $updates = $this->prescriptionAttributeNormalizer->normalizePatch($prescription, $attributes);

        if ($updates === []) {
            return $prescription;
        }

        $updated = $this->prescriptionRepository->update($tenantId, $prescriptionId, $updates);

        if (! $updated instanceof PrescriptionData) {
            throw new LogicException('Updated prescription could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'prescriptions.updated',
            objectType: 'prescription',
            objectId: $updated->prescriptionId,
            before: $prescription->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function prescriptionOrFail(string $prescriptionId): PrescriptionData
    {
        $prescription = $this->prescriptionRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $prescriptionId,
        );

        if (! $prescription instanceof PrescriptionData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $prescription;
    }
}
