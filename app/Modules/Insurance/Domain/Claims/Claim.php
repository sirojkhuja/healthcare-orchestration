<?php

namespace App\Modules\Insurance\Domain\Claims;

use DateTimeImmutable;

final class Claim
{
    private ClaimStatus $status;

    private ?ClaimTransitionData $lastTransition = null;

    /** @var list<array<string, mixed>> */
    private array $adjudicationHistory = [];

    private ?DateTimeImmutable $submittedAt = null;

    private ?DateTimeImmutable $reviewStartedAt = null;

    private ?DateTimeImmutable $approvedAt = null;

    private ?DateTimeImmutable $deniedAt = null;

    private ?DateTimeImmutable $paidAt = null;

    private ?string $approvedAmount = null;

    private ?string $paidAmount = null;

    private ?string $denialReason = null;

    private function __construct(
        private readonly string $claimId,
        private readonly string $tenantId,
        private readonly string $billedAmount,
        ClaimStatus $status,
    ) {
        $this->status = $status;
    }

    /**
     * @param  list<array<string, mixed>>  $adjudicationHistory
     */
    public static function reconstitute(
        string $claimId,
        string $tenantId,
        string $billedAmount,
        ClaimStatus $status,
        ?ClaimTransitionData $lastTransition = null,
        array $adjudicationHistory = [],
        ?DateTimeImmutable $submittedAt = null,
        ?DateTimeImmutable $reviewStartedAt = null,
        ?DateTimeImmutable $approvedAt = null,
        ?DateTimeImmutable $deniedAt = null,
        ?DateTimeImmutable $paidAt = null,
        ?string $approvedAmount = null,
        ?string $paidAmount = null,
        ?string $denialReason = null,
    ): self {
        $claim = new self($claimId, $tenantId, $billedAmount, $status);
        $claim->lastTransition = $lastTransition;
        $claim->adjudicationHistory = $adjudicationHistory;
        $claim->submittedAt = $submittedAt;
        $claim->reviewStartedAt = $reviewStartedAt;
        $claim->approvedAt = $approvedAt;
        $claim->deniedAt = $deniedAt;
        $claim->paidAt = $paidAt;
        $claim->approvedAmount = $approvedAmount;
        $claim->paidAmount = $paidAmount;
        $claim->denialReason = $denialReason;

        return $claim;
    }

    public function approve(DateTimeImmutable $occurredAt, ClaimActor $actor, string $reason, string $sourceEvidence, string $approvedAmount): void
    {
        ClaimTransitionRules::assertDecisionMetadata($reason, $sourceEvidence);
        ClaimTransitionRules::assertCanApprove($this->status, $approvedAmount, $this->billedAmount);

        $this->approvedAt = $occurredAt;
        $this->deniedAt = null;
        $this->paidAt = null;
        $this->reviewStartedAt ??= $occurredAt;
        $this->denialReason = null;
        $this->approvedAmount = $approvedAmount;
        $this->paidAmount = null;
        $this->appendAdjudication('approved', $occurredAt, $actor, $reason, $sourceEvidence, $approvedAmount);
        $this->applyTransition(ClaimStatus::APPROVED, $occurredAt, $actor, $reason, $sourceEvidence, $approvedAmount);
    }

    public function deny(DateTimeImmutable $occurredAt, ClaimActor $actor, string $reason, string $sourceEvidence): void
    {
        ClaimTransitionRules::assertDecisionMetadata($reason, $sourceEvidence);
        ClaimTransitionRules::assertCanDeny($this->status);

        $this->deniedAt = $occurredAt;
        $this->approvedAt = null;
        $this->paidAt = null;
        $this->approvedAmount = null;
        $this->paidAmount = null;
        $this->denialReason = trim($reason);
        $this->appendAdjudication('denied', $occurredAt, $actor, $reason, $sourceEvidence);
        $this->applyTransition(ClaimStatus::DENIED, $occurredAt, $actor, $reason, $sourceEvidence);
    }

    public function markPaid(DateTimeImmutable $occurredAt, ClaimActor $actor, string $reason, string $sourceEvidence, string $paidAmount): void
    {
        ClaimTransitionRules::assertDecisionMetadata($reason, $sourceEvidence);
        ClaimTransitionRules::assertCanMarkPaid($this->status, $paidAmount, $this->approvedAmount);

        $this->paidAt = $occurredAt;
        $this->paidAmount = $paidAmount;
        $this->appendAdjudication('paid', $occurredAt, $actor, $reason, $sourceEvidence, $paidAmount);
        $this->applyTransition(ClaimStatus::PAID, $occurredAt, $actor, $reason, $sourceEvidence, $paidAmount);
    }

    public function reopen(DateTimeImmutable $occurredAt, ClaimActor $actor, string $reason, string $sourceEvidence): void
    {
        ClaimTransitionRules::assertDecisionMetadata($reason, $sourceEvidence);
        ClaimTransitionRules::assertCanReopen($this->status);

        $this->appendAdjudication('reopened', $occurredAt, $actor, $reason, $sourceEvidence);
        $this->submittedAt = $occurredAt;
        $this->reviewStartedAt = null;
        $this->approvedAt = null;
        $this->deniedAt = null;
        $this->paidAt = null;
        $this->approvedAmount = null;
        $this->paidAmount = null;
        $this->denialReason = null;
        $this->applyTransition(ClaimStatus::SUBMITTED, $occurredAt, $actor, $reason, $sourceEvidence);
    }

    /**
     * @return array{
     *     claim_id: string,
     *     tenant_id: string,
     *     status: string,
     *     approved_amount: string|null,
     *     paid_amount: string|null,
     *     denial_reason: string|null,
     *     submitted_at: string|null,
     *     review_started_at: string|null,
     *     approved_at: string|null,
     *     denied_at: string|null,
     *     paid_at: string|null,
     *     last_transition: array<string, mixed>|null,
     *     adjudication_history: list<array<string, mixed>>
     * }
     */
    public function snapshot(): array
    {
        return [
            'claim_id' => $this->claimId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status->value,
            'approved_amount' => $this->approvedAmount,
            'paid_amount' => $this->paidAmount,
            'denial_reason' => $this->denialReason,
            'submitted_at' => $this->submittedAt?->format(DATE_ATOM),
            'review_started_at' => $this->reviewStartedAt?->format(DATE_ATOM),
            'approved_at' => $this->approvedAt?->format(DATE_ATOM),
            'denied_at' => $this->deniedAt?->format(DATE_ATOM),
            'paid_at' => $this->paidAt?->format(DATE_ATOM),
            'last_transition' => $this->lastTransition?->toArray(),
            'adjudication_history' => $this->adjudicationHistory,
        ];
    }

    public function startReview(DateTimeImmutable $occurredAt, ClaimActor $actor, string $reason, string $sourceEvidence): void
    {
        ClaimTransitionRules::assertDecisionMetadata($reason, $sourceEvidence);
        ClaimTransitionRules::assertCanStartReview($this->status);

        $this->reviewStartedAt = $occurredAt;
        $this->applyTransition(ClaimStatus::UNDER_REVIEW, $occurredAt, $actor, $reason, $sourceEvidence);
    }

    public function status(): ClaimStatus
    {
        return $this->status;
    }

    public function submit(DateTimeImmutable $occurredAt, ClaimActor $actor): void
    {
        ClaimTransitionRules::assertCanSubmit($this->status);

        $this->submittedAt = $occurredAt;
        $this->reviewStartedAt = null;
        $this->applyTransition(ClaimStatus::SUBMITTED, $occurredAt, $actor);
    }

    private function applyTransition(
        ClaimStatus $toStatus,
        DateTimeImmutable $occurredAt,
        ClaimActor $actor,
        ?string $reason = null,
        ?string $sourceEvidence = null,
        ?string $amount = null,
    ): void {
        $this->lastTransition = new ClaimTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
            sourceEvidence: $sourceEvidence,
            amount: $amount,
        );
        $this->status = $toStatus;
    }

    private function appendAdjudication(
        string $decision,
        DateTimeImmutable $occurredAt,
        ClaimActor $actor,
        string $reason,
        string $sourceEvidence,
        ?string $amount = null,
    ): void {
        $this->adjudicationHistory[] = [
            'decision' => $decision,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
            'actor' => $actor->toArray(),
            'reason' => trim($reason),
            'source_evidence' => trim($sourceEvidence),
            'amount' => $amount,
        ];
    }
}
