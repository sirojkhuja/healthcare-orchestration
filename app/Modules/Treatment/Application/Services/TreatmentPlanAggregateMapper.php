<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlan;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanStatus;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanTransitionData;

final class TreatmentPlanAggregateMapper
{
    public function fromData(TreatmentPlanData $plan): TreatmentPlan
    {
        return TreatmentPlan::reconstitute(
            planId: $plan->planId,
            tenantId: $plan->tenantId,
            patientId: $plan->patientId,
            providerId: $plan->providerId,
            title: $plan->title,
            summary: $plan->summary,
            goals: $plan->goals,
            plannedStartDate: $plan->plannedStartDate,
            plannedEndDate: $plan->plannedEndDate,
            status: TreatmentPlanStatus::from($plan->status),
            lastTransition: $plan->lastTransition !== null
                ? TreatmentPlanTransitionData::fromArray($this->transitionPayload($plan->lastTransition))
                : null,
            approvedAt: $plan->approvedAt?->toDateTimeImmutable(),
            startedAt: $plan->startedAt?->toDateTimeImmutable(),
            pausedAt: $plan->pausedAt?->toDateTimeImmutable(),
            finishedAt: $plan->finishedAt?->toDateTimeImmutable(),
            rejectedAt: $plan->rejectedAt?->toDateTimeImmutable(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed
     * }
     */
    private function transitionPayload(array $payload): array
    {
        return [
            'from_status' => $payload['from_status'] ?? null,
            'to_status' => $payload['to_status'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? null,
            'actor' => $payload['actor'] ?? null,
            'reason' => $payload['reason'] ?? null,
        ];
    }
}
