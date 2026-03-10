<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\BillableServiceRepository;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Data\BillableServiceListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class BillableServiceCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BillableServiceRepository $billableServiceRepository,
        private readonly BillableServiceAttributeNormalizer $billableServiceAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BillableServiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->billableServiceAttributeNormalizer->normalizeCreate($attributes);
        /** @var mixed $candidateCode */
        $candidateCode = $normalized['code'] ?? null;
        $code = is_string($candidateCode) ? $candidateCode : '';
        $this->assertUniqueCode($tenantId, $code);
        $service = $this->billableServiceRepository->create($tenantId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'billable_services.created',
            objectType: 'billable_service',
            objectId: $service->serviceId,
            after: $service->toArray(),
        ));

        return $service;
    }

    public function delete(string $serviceId): BillableServiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $service = $this->serviceOrFail($serviceId);

        if ($this->billableServiceRepository->isReferencedInPriceLists($tenantId, $serviceId)) {
            throw new ConflictHttpException('Referenced billable services cannot be deleted while price list items still exist.');
        }

        if (! $this->billableServiceRepository->delete($tenantId, $serviceId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'billable_services.deleted',
            objectType: 'billable_service',
            objectId: $service->serviceId,
            before: $service->toArray(),
        ));

        return $service;
    }

    /**
     * @return list<BillableServiceData>
     */
    public function list(BillableServiceListCriteria $criteria): array
    {
        return $this->billableServiceRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $serviceId, array $attributes): BillableServiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $service = $this->serviceOrFail($serviceId);
        $updates = $this->billableServiceAttributeNormalizer->normalizePatch($service, $attributes);

        if ($updates === []) {
            return $service;
        }

        /** @var mixed $candidateCode */
        $candidateCode = $updates['code'] ?? $service->code;
        $code = is_string($candidateCode) ? $candidateCode : $service->code;
        $this->assertUniqueCode($tenantId, $code, $service->serviceId);
        $updated = $this->billableServiceRepository->update($tenantId, $serviceId, $updates);

        if (! $updated instanceof BillableServiceData) {
            throw new \LogicException('Updated billable service could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'billable_services.updated',
            objectType: 'billable_service',
            objectId: $updated->serviceId,
            before: $service->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function assertUniqueCode(string $tenantId, string $code, ?string $ignoreServiceId = null): void
    {
        if ($this->billableServiceRepository->codeExists($tenantId, $code, $ignoreServiceId)) {
            throw new UnprocessableEntityHttpException('The code field must be unique in the current tenant.');
        }
    }

    private function serviceOrFail(string $serviceId): BillableServiceData
    {
        $service = $this->billableServiceRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $serviceId,
        );

        if (! $service instanceof BillableServiceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $service;
    }
}
