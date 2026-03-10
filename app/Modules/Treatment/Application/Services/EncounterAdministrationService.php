<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Domain\Encounters\EncounterStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EncounterAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EncounterRepository $encounterRepository,
        private readonly EncounterAttributeNormalizer $encounterAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EncounterData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterRepository->create(
            $tenantId,
            $this->encounterAttributeNormalizer->normalizeCreate($attributes, $tenantId),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounters.created',
            objectType: 'encounter',
            objectId: $encounter->encounterId,
            after: $encounter->toArray(),
        ));

        return $encounter;
    }

    public function delete(string $encounterId): EncounterData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);

        if (! in_array($encounter->status, [EncounterStatus::OPEN->value, EncounterStatus::ENTERED_IN_ERROR->value], true)) {
            throw new ConflictHttpException('Only open or entered_in_error encounters may be deleted.');
        }

        $deletedAt = CarbonImmutable::now();

        if (! $this->encounterRepository->softDelete($tenantId, $encounter->encounterId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->encounterRepository->findInTenant($tenantId, $encounter->encounterId, true);

        if (! $deleted instanceof EncounterData) {
            throw new LogicException('Deleted encounter could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounters.deleted',
            objectType: 'encounter',
            objectId: $encounter->encounterId,
            before: $encounter->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $encounterId): EncounterData
    {
        return $this->encounterOrFail($encounterId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $encounterId, array $attributes): EncounterData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);
        $updates = $this->encounterAttributeNormalizer->normalizePatch($encounter, $attributes);

        if ($updates === []) {
            return $encounter;
        }

        $updated = $this->encounterRepository->update($tenantId, $encounter->encounterId, $updates);

        if (! $updated instanceof EncounterData) {
            throw new LogicException('Updated encounter could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounters.updated',
            objectType: 'encounter',
            objectId: $encounter->encounterId,
            before: $encounter->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function encounterOrFail(string $encounterId): EncounterData
    {
        $encounter = $this->encounterRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $encounterId,
        );

        if (! $encounter instanceof EncounterData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $encounter;
    }
}
