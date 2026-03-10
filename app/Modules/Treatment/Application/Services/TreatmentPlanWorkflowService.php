<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\TreatmentPlanRepository;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Domain\TreatmentPlans\InvalidTreatmentPlanTransition;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlan;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanActor;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TreatmentPlanWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TreatmentPlanRepository $treatmentPlanRepository,
        private readonly TreatmentPlanAggregateMapper $treatmentPlanAggregateMapper,
        private readonly TreatmentPlanActorContext $treatmentPlanActorContext,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function approve(string $planId): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.approved',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor): void {
                $plan->approve($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function finish(string $planId): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.finished',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor): void {
                $plan->finish($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function pause(string $planId, string $reason): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.paused',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor) use ($reason): void {
                $plan->pause($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    public function reject(string $planId, string $reason): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.rejected',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor) use ($reason): void {
                $plan->reject($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    public function resume(string $planId): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.resumed',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor): void {
                $plan->resume($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function start(string $planId): TreatmentPlanData
    {
        return $this->transition(
            planId: $planId,
            auditAction: 'treatment_plans.started',
            mutator: static function (TreatmentPlan $plan, CarbonImmutable $occurredAt, TreatmentPlanActor $actor): void {
                $plan->start($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    /**
     * @param  callable(TreatmentPlan, CarbonImmutable, TreatmentPlanActor): void  $mutator
     */
    private function transition(string $planId, string $auditAction, callable $mutator): TreatmentPlanData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->planOrFail($tenantId, $planId);
        $actor = $this->treatmentPlanActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var TreatmentPlanData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): TreatmentPlanData {
            $aggregate = $this->treatmentPlanAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidTreatmentPlanTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $snapshot = $aggregate->snapshot();
            $updated = $this->treatmentPlanRepository->update($tenantId, $before->planId, $snapshot);

            if (! $updated instanceof TreatmentPlanData) {
                throw new LogicException('Updated treatment plan could not be reloaded.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'treatment_plan',
            objectId: $updated->planId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function planOrFail(string $tenantId, string $planId): TreatmentPlanData
    {
        $plan = $this->treatmentPlanRepository->findInTenant($tenantId, $planId);

        if (! $plan instanceof TreatmentPlanData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $plan;
    }
}
