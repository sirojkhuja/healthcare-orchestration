<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\TreatmentPlanRepository;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TreatmentPlanAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TreatmentPlanRepository $treatmentPlanRepository,
        private readonly TreatmentPlanAttributeNormalizer $treatmentPlanAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TreatmentPlanData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->treatmentPlanAttributeNormalizer->normalizeCreate($attributes, $tenantId);
        $plan = $this->treatmentPlanRepository->create($tenantId, [
            ...$normalized,
            'status' => TreatmentPlanStatus::DRAFT->value,
            'last_transition' => null,
            'approved_at' => null,
            'started_at' => null,
            'paused_at' => null,
            'finished_at' => null,
            'rejected_at' => null,
        ]);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plans.created',
            objectType: 'treatment_plan',
            objectId: $plan->planId,
            after: $plan->toArray(),
        ));

        return $plan;
    }

    public function delete(string $planId): TreatmentPlanData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $plan = $this->planOrFail($planId);

        if (! in_array($plan->status, [TreatmentPlanStatus::DRAFT->value, TreatmentPlanStatus::REJECTED->value], true)) {
            throw new ConflictHttpException('Only draft or rejected treatment plans may be deleted.');
        }

        $deletedAt = CarbonImmutable::now();

        if (! $this->treatmentPlanRepository->softDelete($tenantId, $plan->planId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->treatmentPlanRepository->findInTenant($tenantId, $plan->planId, true);

        if (! $deleted instanceof TreatmentPlanData) {
            throw new LogicException('Deleted treatment plan could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plans.deleted',
            objectType: 'treatment_plan',
            objectId: $plan->planId,
            before: $plan->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $planId): TreatmentPlanData
    {
        return $this->planOrFail($planId);
    }

    /**
     * @return list<TreatmentPlanData>
     */
    public function list(): array
    {
        return $this->treatmentPlanRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $planId, array $attributes): TreatmentPlanData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $plan = $this->planOrFail($planId);

        if (! in_array($plan->status, [TreatmentPlanStatus::DRAFT->value, TreatmentPlanStatus::APPROVED->value], true)) {
            throw new ConflictHttpException('Only draft or approved treatment plans may be updated.');
        }

        $updates = $this->treatmentPlanAttributeNormalizer->normalizePatch($plan, $attributes);

        if ($updates === []) {
            return $plan;
        }

        $updated = $this->treatmentPlanRepository->update($tenantId, $plan->planId, $updates);

        if (! $updated instanceof TreatmentPlanData) {
            throw new LogicException('Updated treatment plan could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plans.updated',
            objectType: 'treatment_plan',
            objectId: $plan->planId,
            before: $plan->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function planOrFail(string $planId): TreatmentPlanData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $plan = $this->treatmentPlanRepository->findInTenant($tenantId, $planId);

        if (! $plan instanceof TreatmentPlanData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $plan;
    }
}
