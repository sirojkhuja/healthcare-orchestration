<?php

use App\Modules\Insurance\Domain\Claims\Claim;
use App\Modules\Insurance\Domain\Claims\ClaimActor;
use App\Modules\Insurance\Domain\Claims\ClaimStatus;
use App\Modules\Insurance\Domain\Claims\InvalidClaimTransition;
use Carbon\CarbonImmutable;

it('supports the documented claim lifecycle including reopen clearing adjudication fields', function (): void {
    $claim = Claim::reconstitute(
        claimId: 'claim-1',
        tenantId: 'tenant-1',
        billedAmount: '120000.00',
        status: ClaimStatus::DRAFT,
    );
    $actor = new ClaimActor('user', 'user-1', 'Reviewer');
    $submittedAt = CarbonImmutable::parse('2026-03-11T10:00:00Z');
    $reviewAt = CarbonImmutable::parse('2026-03-11T11:00:00Z');
    $approvedAt = CarbonImmutable::parse('2026-03-11T12:00:00Z');
    $paidAt = CarbonImmutable::parse('2026-03-11T13:00:00Z');
    $reopenedAt = CarbonImmutable::parse('2026-03-11T14:00:00Z');

    $claim->submit($submittedAt->toDateTimeImmutable(), $actor);
    $claim->startReview($reviewAt->toDateTimeImmutable(), $actor, 'Ready', 'Checklist');
    $claim->approve($approvedAt->toDateTimeImmutable(), $actor, 'Approved', 'Sheet', '110000.00');
    $claim->markPaid($paidAt->toDateTimeImmutable(), $actor, 'Settled', 'Transfer batch', '110000.00');
    $claim->reopen($reopenedAt->toDateTimeImmutable(), $actor, 'Appeal', 'Appeal package');

    $snapshot = $claim->snapshot();

    expect($snapshot['status'])->toBe('submitted');
    expect($snapshot['approved_amount'])->toBeNull();
    expect($snapshot['paid_amount'])->toBeNull();
    expect($snapshot['review_started_at'])->toBeNull();
    expect($snapshot['approved_at'])->toBeNull();
    expect($snapshot['paid_at'])->toBeNull();
    expect($snapshot['submitted_at'])->toBe($reopenedAt->toIso8601String());
    expect($snapshot['adjudication_history'])->toHaveCount(3);
    expect($snapshot['last_transition']['to_status'])->toBe('submitted');
});

it('rejects invalid claim transitions and amount guards', function (): void {
    $claim = Claim::reconstitute(
        claimId: 'claim-2',
        tenantId: 'tenant-1',
        billedAmount: '100000.00',
        status: ClaimStatus::DRAFT,
    );
    $actor = new ClaimActor('user', 'user-2', 'Reviewer');

    expect(fn () => $claim->approve(
        CarbonImmutable::parse('2026-03-11T12:00:00Z')->toDateTimeImmutable(),
        $actor,
        'Invalid',
        'Evidence',
        '100000.00',
    ))->toThrow(InvalidClaimTransition::class);

    $claim->submit(CarbonImmutable::parse('2026-03-11T10:00:00Z')->toDateTimeImmutable(), $actor);
    $claim->startReview(CarbonImmutable::parse('2026-03-11T11:00:00Z')->toDateTimeImmutable(), $actor, 'Ready', 'Checklist');

    expect(fn () => $claim->approve(
        CarbonImmutable::parse('2026-03-11T12:00:00Z')->toDateTimeImmutable(),
        $actor,
        'Too much',
        'Evidence',
        '100000.01',
    ))->toThrow(InvalidClaimTransition::class);
});
