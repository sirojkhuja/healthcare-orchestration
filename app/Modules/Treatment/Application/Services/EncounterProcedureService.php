<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\EncounterProcedureRepository;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Contracts\TreatmentItemRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentItemType;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EncounterProcedureService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EncounterRepository $encounterRepository,
        private readonly EncounterProcedureRepository $encounterProcedureRepository,
        private readonly EncounterProcedureAttributeNormalizer $encounterProcedureAttributeNormalizer,
        private readonly TreatmentItemRepository $treatmentItemRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $encounterId, array $attributes): EncounterProcedureData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);
        $normalized = $this->encounterProcedureAttributeNormalizer->normalizeCreate($attributes);

        $this->assertTreatmentItemLink($encounter, $normalized['treatment_item_id']);

        if ($this->encounterProcedureRepository->duplicateExists(
            $tenantId,
            $encounter->encounterId,
            $normalized['code'],
            $normalized['display_name'],
            $normalized['performed_at'],
            $normalized['treatment_item_id'],
        )) {
            throw new ConflictHttpException('The requested procedure already exists on this encounter.');
        }

        $procedure = $this->encounterProcedureRepository->create($tenantId, $encounter->encounterId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounter_procedures.added',
            objectType: 'encounter_procedure',
            objectId: $procedure->procedureId,
            after: $procedure->toArray(),
            metadata: [
                'encounter_id' => $encounter->encounterId,
            ],
        ));

        return $procedure;
    }

    public function delete(string $encounterId, string $procedureId): EncounterProcedureData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);
        $procedure = $this->encounterProcedureRepository->findInEncounter($tenantId, $encounter->encounterId, $procedureId);

        if (! $procedure instanceof EncounterProcedureData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        if (! $this->encounterProcedureRepository->delete($tenantId, $encounter->encounterId, $procedureId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounter_procedures.removed',
            objectType: 'encounter_procedure',
            objectId: $procedure->procedureId,
            before: $procedure->toArray(),
            metadata: [
                'encounter_id' => $encounter->encounterId,
            ],
        ));

        return $procedure;
    }

    /**
     * @return list<EncounterProcedureData>
     */
    public function list(string $encounterId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);

        return $this->encounterProcedureRepository->listForEncounter($tenantId, $encounter->encounterId);
    }

    private function assertTreatmentItemLink(EncounterData $encounter, ?string $treatmentItemId): void
    {
        if ($treatmentItemId === null) {
            return;
        }

        if ($encounter->treatmentPlanId === null) {
            throw new UnprocessableEntityHttpException('The treatment_item_id field requires the encounter to be linked to a treatment plan.');
        }

        $item = $this->treatmentItemRepository->findInPlan(
            $encounter->tenantId,
            $encounter->treatmentPlanId,
            $treatmentItemId,
        );

        if ($item === null) {
            throw new UnprocessableEntityHttpException('The treatment_item_id field must reference a treatment item in the linked treatment plan.');
        }

        if ($item->itemType !== TreatmentItemType::PROCEDURE->value) {
            throw new UnprocessableEntityHttpException('The treatment_item_id field must reference a treatment item with item_type procedure.');
        }
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
