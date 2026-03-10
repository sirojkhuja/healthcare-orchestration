<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

use DateTimeImmutable;

final class TreatmentPlan
{
    private TreatmentPlanStatus $status;

    private ?TreatmentPlanTransitionData $lastTransition = null;

    private ?DateTimeImmutable $approvedAt = null;

    private ?DateTimeImmutable $startedAt = null;

    private ?DateTimeImmutable $pausedAt = null;

    private ?DateTimeImmutable $finishedAt = null;

    private ?DateTimeImmutable $rejectedAt = null;

    /** @var list<TreatmentPlanDomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        private string $planId,
        private string $tenantId,
        private string $patientId,
        private string $providerId,
        private string $title,
        private ?string $summary,
        private ?string $goals,
        private ?string $plannedStartDate,
        private ?string $plannedEndDate,
        TreatmentPlanStatus $status,
    ) {
        $this->status = $status;
    }

    public static function draft(
        string $planId,
        string $tenantId,
        string $patientId,
        string $providerId,
        string $title,
        ?string $summary = null,
        ?string $goals = null,
        ?string $plannedStartDate = null,
        ?string $plannedEndDate = null,
    ): self {
        return new self(
            planId: $planId,
            tenantId: $tenantId,
            patientId: $patientId,
            providerId: $providerId,
            title: $title,
            summary: $summary,
            goals: $goals,
            plannedStartDate: $plannedStartDate,
            plannedEndDate: $plannedEndDate,
            status: TreatmentPlanStatus::DRAFT,
        );
    }

    public static function reconstitute(
        string $planId,
        string $tenantId,
        string $patientId,
        string $providerId,
        string $title,
        ?string $summary,
        ?string $goals,
        ?string $plannedStartDate,
        ?string $plannedEndDate,
        TreatmentPlanStatus $status,
        ?TreatmentPlanTransitionData $lastTransition = null,
        ?DateTimeImmutable $approvedAt = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $pausedAt = null,
        ?DateTimeImmutable $finishedAt = null,
        ?DateTimeImmutable $rejectedAt = null,
    ): self {
        $plan = new self(
            planId: $planId,
            tenantId: $tenantId,
            patientId: $patientId,
            providerId: $providerId,
            title: $title,
            summary: $summary,
            goals: $goals,
            plannedStartDate: $plannedStartDate,
            plannedEndDate: $plannedEndDate,
            status: $status,
        );
        $plan->lastTransition = $lastTransition;
        $plan->approvedAt = $approvedAt;
        $plan->startedAt = $startedAt;
        $plan->pausedAt = $pausedAt;
        $plan->finishedAt = $finishedAt;
        $plan->rejectedAt = $rejectedAt;

        return $plan;
    }

    public function approve(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor): void
    {
        TreatmentPlanTransitionRules::assertCanApprove($this->status);
        $this->approvedAt = $occurredAt;
        $this->applyTransition(TreatmentPlanStatus::APPROVED, TreatmentPlanEventType::APPROVED, $occurredAt, $actor);
    }

    public function finish(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor): void
    {
        TreatmentPlanTransitionRules::assertCanFinish($this->status);
        $this->pausedAt = null;
        $this->finishedAt = $occurredAt;
        $this->applyTransition(TreatmentPlanStatus::FINISHED, TreatmentPlanEventType::FINISHED, $occurredAt, $actor);
    }

    public function pause(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor, string $reason): void
    {
        TreatmentPlanTransitionRules::assertCanPause($this->status, $reason);
        $this->pausedAt = $occurredAt;
        $this->applyTransition(TreatmentPlanStatus::PAUSED, TreatmentPlanEventType::PAUSED, $occurredAt, $actor, $reason);
    }

    public function reject(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor, string $reason): void
    {
        TreatmentPlanTransitionRules::assertCanReject($this->status, $reason);
        $this->rejectedAt = $occurredAt;
        $this->applyTransition(TreatmentPlanStatus::REJECTED, TreatmentPlanEventType::REJECTED, $occurredAt, $actor, $reason);
    }

    /** @return list<TreatmentPlanDomainEvent> */
    public function releaseRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function resume(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor): void
    {
        TreatmentPlanTransitionRules::assertCanResume($this->status);
        $this->pausedAt = null;
        $this->applyTransition(TreatmentPlanStatus::ACTIVE, TreatmentPlanEventType::RESUMED, $occurredAt, $actor);
    }

    public function start(DateTimeImmutable $occurredAt, TreatmentPlanActor $actor): void
    {
        TreatmentPlanTransitionRules::assertCanStart($this->status);
        $this->startedAt = $occurredAt;
        $this->applyTransition(TreatmentPlanStatus::ACTIVE, TreatmentPlanEventType::STARTED, $occurredAt, $actor);
    }

    /**
     * @return array{
     *     title: string,
     *     summary: string|null,
     *     goals: string|null,
     *     planned_start_date: string|null,
     *     planned_end_date: string|null,
     *     status: string,
     *     last_transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null
     *     }|null,
     *     approved_at: string|null,
     *     started_at: string|null,
     *     paused_at: string|null,
     *     finished_at: string|null,
     *     rejected_at: string|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'goals' => $this->goals,
            'planned_start_date' => $this->plannedStartDate,
            'planned_end_date' => $this->plannedEndDate,
            'status' => $this->status->value,
            'last_transition' => $this->lastTransition?->toArray(),
            'approved_at' => $this->approvedAt?->format(DATE_ATOM),
            'started_at' => $this->startedAt?->format(DATE_ATOM),
            'paused_at' => $this->pausedAt?->format(DATE_ATOM),
            'finished_at' => $this->finishedAt?->format(DATE_ATOM),
            'rejected_at' => $this->rejectedAt?->format(DATE_ATOM),
        ];
    }

    public function status(): TreatmentPlanStatus
    {
        return $this->status;
    }

    private function applyTransition(
        TreatmentPlanStatus $toStatus,
        TreatmentPlanEventType $eventType,
        DateTimeImmutable $occurredAt,
        TreatmentPlanActor $actor,
        ?string $reason = null,
    ): void {
        $transition = new TreatmentPlanTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
        );
        $this->status = $toStatus;
        $this->lastTransition = $transition;
        $this->recordedEvents[] = new TreatmentPlanDomainEvent(
            type: $eventType,
            planId: $this->planId,
            tenantId: $this->tenantId,
            patientId: $this->patientId,
            providerId: $this->providerId,
            title: $this->title,
            status: $this->status,
            transition: $transition,
        );
    }
}
