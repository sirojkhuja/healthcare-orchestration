<?php

namespace App\Modules\Insurance\Domain\Claims;

use LogicException;

final class ClaimTransitionRules
{
    public static function assertCanApprove(ClaimStatus $status, string $approvedAmount, string $billedAmount): void
    {
        if ($status !== ClaimStatus::UNDER_REVIEW) {
            throw new InvalidClaimTransition('Only claims under review may be approved.');
        }

        self::assertPositiveAmount($approvedAmount, 'approved');

        if (self::compare($approvedAmount, $billedAmount) === 1) {
            throw new InvalidClaimTransition('Approved amount may not exceed billed amount.');
        }
    }

    public static function assertCanDeny(ClaimStatus $status): void
    {
        if ($status !== ClaimStatus::UNDER_REVIEW) {
            throw new InvalidClaimTransition('Only claims under review may be denied.');
        }
    }

    public static function assertCanMarkPaid(ClaimStatus $status, string $paidAmount, ?string $approvedAmount): void
    {
        if ($status !== ClaimStatus::APPROVED) {
            throw new InvalidClaimTransition('Only approved claims may be marked paid.');
        }

        self::assertPositiveAmount($paidAmount, 'paid');
        $upperBound = $approvedAmount ?? '0.00';

        if (self::compare($paidAmount, $upperBound) === 1) {
            throw new InvalidClaimTransition('Paid amount may not exceed approved amount.');
        }
    }

    public static function assertCanReopen(ClaimStatus $status): void
    {
        if (! in_array($status, [ClaimStatus::APPROVED, ClaimStatus::DENIED, ClaimStatus::PAID], true)) {
            throw new InvalidClaimTransition('Only adjudicated claims may be reopened.');
        }
    }

    public static function assertCanStartReview(ClaimStatus $status): void
    {
        if ($status !== ClaimStatus::SUBMITTED) {
            throw new InvalidClaimTransition('Only submitted claims may enter review.');
        }
    }

    public static function assertCanSubmit(ClaimStatus $status): void
    {
        if ($status !== ClaimStatus::DRAFT) {
            throw new InvalidClaimTransition('Only draft claims may be submitted.');
        }
    }

    public static function assertDecisionMetadata(string $reason, string $sourceEvidence): void
    {
        if (trim($reason) === '') {
            throw new InvalidClaimTransition('A non-empty reason is required for this claim action.');
        }

        if (trim($sourceEvidence) === '') {
            throw new InvalidClaimTransition('Source evidence is required for this claim action.');
        }
    }

    private static function assertPositiveAmount(string $amount, string $label): void
    {
        if (self::compare($amount, '0.00') <= 0) {
            throw new InvalidClaimTransition(ucfirst($label).' amount must be positive.');
        }
    }

    private static function compare(string $left, string $right): int
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        /** @phpstan-ignore-next-line */
        return bccomp(self::normalizeDecimal($left), self::normalizeDecimal($right), 2);
    }

    private static function normalizeDecimal(string $value): string
    {
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            throw new LogicException('Claim transition amounts must be valid decimal strings.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
