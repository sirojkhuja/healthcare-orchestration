<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\ClaimRepository;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Domain\Claims\Claim;
use App\Modules\Insurance\Domain\Claims\ClaimActor;
use App\Modules\Insurance\Domain\Claims\InvalidClaimTransition;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClaimRepository $claimRepository,
        private readonly ClaimAggregateMapper $claimAggregateMapper,
        private readonly ClaimActorContext $claimActorContext,
        private readonly ClaimRuleEvaluator $claimRuleEvaluator,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly ClaimOutboxPublisher $claimOutboxPublisher,
    ) {}

    public function approve(string $claimId, string $approvedAmount, string $reason, string $sourceEvidence): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.approved',
            eventType: 'claim.approved',
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor) use ($approvedAmount, $reason, $sourceEvidence): void {
                $claim->approve(
                    $occurredAt->toDateTimeImmutable(),
                    $actor,
                    $reason,
                    $sourceEvidence,
                    $approvedAmount,
                );
            },
        );
    }

    public function deny(string $claimId, string $reason, string $sourceEvidence): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.denied',
            eventType: 'claim.denied',
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor) use ($reason, $sourceEvidence): void {
                $claim->deny($occurredAt->toDateTimeImmutable(), $actor, $reason, $sourceEvidence);
            },
        );
    }

    public function markPaid(string $claimId, string $paidAmount, string $reason, string $sourceEvidence): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.paid',
            eventType: 'claim.paid',
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor) use ($paidAmount, $reason, $sourceEvidence): void {
                $claim->markPaid(
                    $occurredAt->toDateTimeImmutable(),
                    $actor,
                    $reason,
                    $sourceEvidence,
                    $paidAmount,
                );
            },
        );
    }

    public function reopen(string $claimId, string $reason, string $sourceEvidence): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.reopened',
            eventType: 'claim.reopened',
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor) use ($reason, $sourceEvidence): void {
                $claim->reopen($occurredAt->toDateTimeImmutable(), $actor, $reason, $sourceEvidence);
            },
        );
    }

    public function startReview(string $claimId, string $reason, string $sourceEvidence): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.review_started',
            eventType: 'claim.review_started',
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor) use ($reason, $sourceEvidence): void {
                $claim->startReview($occurredAt->toDateTimeImmutable(), $actor, $reason, $sourceEvidence);
            },
        );
    }

    public function submit(string $claimId): ClaimData
    {
        return $this->transition(
            claimId: $claimId,
            auditAction: 'claims.submitted',
            eventType: 'claim.submitted',
            beforeMutate: function (ClaimData $claim): void {
                $this->claimRuleEvaluator->assertCanSubmit($claim);
            },
            mutator: static function (Claim $claim, CarbonImmutable $occurredAt, ClaimActor $actor): void {
                $claim->submit($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    /**
     * @param  callable(ClaimData): void|null  $beforeMutate
     * @param  callable(Claim, CarbonImmutable, ClaimActor): void  $mutator
     */
    private function transition(
        string $claimId,
        string $auditAction,
        string $eventType,
        callable $mutator,
        ?callable $beforeMutate = null,
    ): ClaimData {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->claimOrFail($tenantId, $claimId);

        if ($beforeMutate !== null) {
            $beforeMutate($before);
        }

        $actor = $this->claimActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var ClaimData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): ClaimData {
            $aggregate = $this->claimAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidClaimTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $updated = $this->claimRepository->update($tenantId, $before->claimId, $aggregate->snapshot());

            if (! $updated instanceof ClaimData) {
                throw new LogicException('Updated claim could not be reloaded.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'claim',
            objectId: $updated->claimId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->claimOutboxPublisher->publishClaimEvent($eventType, $updated);

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
