<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\ClaimRepository;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Domain\Claims\ClaimStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClaimRepository $claimRepository,
        private readonly ClaimAttributeNormalizer $claimAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly ClaimOutboxPublisher $claimOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ClaimData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $claim = $this->claimRepository->create(
            $tenantId,
            $this->claimAttributeNormalizer->normalizeCreate($attributes, $this->claimRepository->allocateClaimNumber($tenantId)),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claims.created',
            objectType: 'claim',
            objectId: $claim->claimId,
            after: $claim->toArray(),
        ));
        $this->claimOutboxPublisher->publishClaimEvent('claim.created', $claim);

        return $claim;
    }

    public function delete(string $claimId): ClaimData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $claim = $this->claimOrFail($tenantId, $claimId);

        if ($claim->status !== ClaimStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft claims may be deleted through the CRUD endpoint.');
        }

        if (! $this->claimRepository->softDelete($tenantId, $claimId, CarbonImmutable::now())) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->claimRepository->findInTenant($tenantId, $claimId, true);

        if (! $deleted instanceof ClaimData) {
            throw new LogicException('Deleted claim could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claims.deleted',
            objectType: 'claim',
            objectId: $deleted->claimId,
            before: $claim->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $claimId, array $attributes): ClaimData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $claim = $this->claimOrFail($tenantId, $claimId);

        if ($claim->status !== ClaimStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft claims may be updated through the CRUD endpoint.');
        }

        $updates = $this->claimAttributeNormalizer->normalizePatch($claim, $attributes);

        if ($updates === []) {
            return $claim;
        }

        $updated = $this->claimRepository->update($tenantId, $claimId, $updates);

        if (! $updated instanceof ClaimData) {
            throw new LogicException('Updated claim could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claims.updated',
            objectType: 'claim',
            objectId: $updated->claimId,
            before: $claim->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function claimOrFail(string $tenantId, string $claimId): ClaimData
    {
        $claim = $this->claimRepository->findInTenant($tenantId, $claimId);

        if (! $claim instanceof ClaimData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $claim;
    }
}
