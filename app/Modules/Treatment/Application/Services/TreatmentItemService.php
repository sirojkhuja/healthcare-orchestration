<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\TreatmentItemRepository;
use App\Modules\Treatment\Application\Contracts\TreatmentPlanRepository;
use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanStatus;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TreatmentItemService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TreatmentPlanRepository $treatmentPlanRepository,
        private readonly TreatmentItemRepository $treatmentItemRepository,
        private readonly TreatmentItemAttributeNormalizer $treatmentItemAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $planId, array $attributes): TreatmentItemData
    {
        $plan = $this->editablePlanOrFail($planId);
        $normalized = $this->treatmentItemAttributeNormalizer->normalizeCreate($attributes);

        /** @var TreatmentItemData $item */
        $item = DB::transaction(function () use ($plan, $normalized): TreatmentItemData {
            $items = $this->treatmentItemRepository->listForPlan($plan->tenantId, $plan->planId);
            $sortOrder = $this->createSortOrder($normalized['sort_order'], count($items));

            foreach ($items as $sibling) {
                if ($sibling->sortOrder >= $sortOrder) {
                    $this->persistSortOrder($plan, $sibling, $sibling->sortOrder + 1);
                }
            }

            return $this->treatmentItemRepository->create($plan->tenantId, $plan->planId, [
                'item_type' => $normalized['item_type'],
                'title' => $normalized['title'],
                'description' => $normalized['description'],
                'instructions' => $normalized['instructions'],
                'sort_order' => $sortOrder,
            ]);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plan_items.created',
            objectType: 'treatment_plan_item',
            objectId: $item->itemId,
            after: $item->toArray(),
            metadata: [
                'plan_id' => $plan->planId,
                'item_id' => $item->itemId,
            ],
        ));

        return $item;
    }

    public function delete(string $planId, string $itemId): TreatmentItemData
    {
        $plan = $this->editablePlanOrFail($planId);
        $item = $this->itemOrFail($plan, $itemId);

        DB::transaction(function () use ($plan, $item): void {
            $items = $this->treatmentItemRepository->listForPlan($plan->tenantId, $plan->planId);

            if (! $this->treatmentItemRepository->delete($plan->tenantId, $plan->planId, $item->itemId)) {
                throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
            }

            foreach ($items as $sibling) {
                if ($sibling->itemId !== $item->itemId && $sibling->sortOrder > $item->sortOrder) {
                    $this->persistSortOrder($plan, $sibling, $sibling->sortOrder - 1);
                }
            }
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plan_items.deleted',
            objectType: 'treatment_plan_item',
            objectId: $item->itemId,
            before: $item->toArray(),
            metadata: [
                'plan_id' => $plan->planId,
                'item_id' => $item->itemId,
            ],
        ));

        return $item;
    }

    /**
     * @return list<TreatmentItemData>
     */
    public function list(string $planId): array
    {
        $plan = $this->planOrFail($planId);

        return $this->treatmentItemRepository->listForPlan($plan->tenantId, $plan->planId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $planId, string $itemId, array $attributes): TreatmentItemData
    {
        $plan = $this->editablePlanOrFail($planId);
        $item = $this->itemOrFail($plan, $itemId);
        $updates = $this->treatmentItemAttributeNormalizer->normalizePatch($item, $attributes);

        if ($updates === []) {
            return $item;
        }

        /** @var TreatmentItemData $updated */
        $updated = DB::transaction(function () use ($plan, $item, $updates): TreatmentItemData {
            $payload = $updates;

            if (array_key_exists('sort_order', $payload)) {
                $targetSortOrder = $this->updateSortOrder($payload['sort_order'], $plan, $item);

                if ($targetSortOrder === $item->sortOrder) {
                    unset($payload['sort_order']);
                } else {
                    $payload['sort_order'] = $targetSortOrder;
                }
            }

            if ($payload === []) {
                return $item;
            }

            $updated = $this->treatmentItemRepository->update($plan->tenantId, $plan->planId, $item->itemId, $payload);

            if (! $updated instanceof TreatmentItemData) {
                throw new LogicException('Updated treatment item could not be reloaded.');
            }

            return $updated;
        });

        if ($updated->toArray() === $item->toArray()) {
            return $item;
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'treatment_plan_items.updated',
            objectType: 'treatment_plan_item',
            objectId: $item->itemId,
            before: $item->toArray(),
            after: $updated->toArray(),
            metadata: [
                'plan_id' => $plan->planId,
                'item_id' => $item->itemId,
            ],
        ));

        return $updated;
    }

    private function assertEditablePlan(TreatmentPlanData $plan): void
    {
        if (! in_array($plan->status, [TreatmentPlanStatus::DRAFT->value, TreatmentPlanStatus::APPROVED->value], true)) {
            throw new ConflictHttpException('Treatment items may only be changed while the parent plan is draft or approved.');
        }
    }

    private function createSortOrder(?int $requestedSortOrder, int $existingCount): int
    {
        if ($requestedSortOrder === null) {
            return $existingCount + 1;
        }

        return max(1, min($requestedSortOrder, $existingCount + 1));
    }

    private function editablePlanOrFail(string $planId): TreatmentPlanData
    {
        $plan = $this->planOrFail($planId);
        $this->assertEditablePlan($plan);

        return $plan;
    }

    private function itemOrFail(TreatmentPlanData $plan, string $itemId): TreatmentItemData
    {
        $item = $this->treatmentItemRepository->findInPlan($plan->tenantId, $plan->planId, $itemId);

        if (! $item instanceof TreatmentItemData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $item;
    }

    private function persistSortOrder(TreatmentPlanData $plan, TreatmentItemData $item, int $sortOrder): void
    {
        $updated = $this->treatmentItemRepository->update($plan->tenantId, $plan->planId, $item->itemId, [
            'sort_order' => $sortOrder,
        ]);

        if (! $updated instanceof TreatmentItemData) {
            throw new LogicException('Treatment item sort order could not be updated.');
        }
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

    private function updateSortOrder(int $requestedSortOrder, TreatmentPlanData $plan, TreatmentItemData $item): int
    {
        $items = $this->treatmentItemRepository->listForPlan($plan->tenantId, $plan->planId);
        $targetSortOrder = max(1, min($requestedSortOrder, count($items)));

        if ($targetSortOrder === $item->sortOrder) {
            return $targetSortOrder;
        }

        foreach ($items as $sibling) {
            if ($sibling->itemId === $item->itemId) {
                continue;
            }

            if (
                $targetSortOrder < $item->sortOrder
                && $sibling->sortOrder >= $targetSortOrder
                && $sibling->sortOrder < $item->sortOrder
            ) {
                $this->persistSortOrder($plan, $sibling, $sibling->sortOrder + 1);
            }

            if (
                $targetSortOrder > $item->sortOrder
                && $sibling->sortOrder <= $targetSortOrder
                && $sibling->sortOrder > $item->sortOrder
            ) {
                $this->persistSortOrder($plan, $sibling, $sibling->sortOrder - 1);
            }
        }

        return $targetSortOrder;
    }
}
