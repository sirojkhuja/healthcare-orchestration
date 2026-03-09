<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PatientAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientAttributeNormalizer $patientAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PatientData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->patientAttributeNormalizer->normalizeCreate($attributes, $tenantId);
        $patient = $this->patientRepository->create($tenantId, $normalized);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.created',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: $patient->toArray(),
        ));

        return $patient;
    }

    public function delete(string $patientId): PatientData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $deletedAt = CarbonImmutable::now();

        if (! $this->patientRepository->softDelete($tenantId, $patient->patientId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->patientRepository->findInTenant($tenantId, $patient->patientId, true);

        if (! $deleted instanceof PatientData) {
            throw new LogicException('Deleted patient could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.deleted',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: $patient->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $patientId): PatientData
    {
        return $this->patientOrFail($patientId);
    }

    /**
     * @return list<PatientData>
     */
    public function list(): array
    {
        return $this->patientRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $patientId, array $attributes): PatientData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $updates = $this->patientAttributeNormalizer->normalizePatch($patient, $attributes);

        if ($updates === []) {
            return $patient;
        }

        $updated = $this->patientRepository->update($tenantId, $patient->patientId, $updates);

        if (! $updated instanceof PatientData) {
            throw new LogicException('Updated patient could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.updated',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: $patient->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function patientOrFail(string $patientId): PatientData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientRepository->findInTenant($tenantId, $patientId);

        if (! $patient instanceof PatientData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $patient;
    }
}
