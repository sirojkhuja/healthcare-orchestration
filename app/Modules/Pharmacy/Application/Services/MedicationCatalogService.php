<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Pharmacy\Application\Contracts\MedicationRepository;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class MedicationCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MedicationRepository $medicationRepository,
        private readonly MedicationAttributeNormalizer $medicationAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MedicationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->medicationAttributeNormalizer->normalizeCreate($attributes);
        $this->assertUniqueCode($tenantId, $normalized['code']);
        $medication = $this->medicationRepository->create($tenantId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'medications.created',
            objectType: 'medication',
            objectId: $medication->medicationId,
            after: $medication->toArray(),
        ));

        return $medication;
    }

    public function delete(string $medicationId): MedicationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $medication = $this->medicationOrFail($medicationId);

        if (! $this->medicationRepository->delete($tenantId, $medicationId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'medications.deleted',
            objectType: 'medication',
            objectId: $medication->medicationId,
            before: $medication->toArray(),
        ));

        return $medication;
    }

    public function get(string $medicationId): MedicationData
    {
        return $this->medicationOrFail($medicationId);
    }

    /**
     * @return list<MedicationData>
     */
    public function list(MedicationListCriteria $criteria): array
    {
        return $this->medicationRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @return list<MedicationData>
     */
    public function search(MedicationListCriteria $criteria): array
    {
        return $this->list($criteria);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $medicationId, array $attributes): MedicationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $medication = $this->medicationOrFail($medicationId);
        $updates = $this->medicationAttributeNormalizer->normalizePatch($medication, $attributes);

        if ($updates === []) {
            return $medication;
        }

        /** @var mixed $candidateCode */
        $candidateCode = $updates['code'] ?? $medication->code;
        $code = is_string($candidateCode) ? $candidateCode : $medication->code;
        $this->assertUniqueCode($tenantId, $code, $medication->medicationId);
        $updated = $this->medicationRepository->update($tenantId, $medicationId, $updates);

        if (! $updated instanceof MedicationData) {
            throw new \LogicException('Updated medication could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'medications.updated',
            objectType: 'medication',
            objectId: $updated->medicationId,
            before: $medication->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function assertUniqueCode(string $tenantId, string $code, ?string $ignoreMedicationId = null): void
    {
        if ($this->medicationRepository->codeExists($tenantId, $code, $ignoreMedicationId)) {
            throw new UnprocessableEntityHttpException('The code field must be unique in the current tenant.');
        }
    }

    private function medicationOrFail(string $medicationId): MedicationData
    {
        $medication = $this->medicationRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $medicationId,
        );

        if (! $medication instanceof MedicationData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $medication;
    }
}
