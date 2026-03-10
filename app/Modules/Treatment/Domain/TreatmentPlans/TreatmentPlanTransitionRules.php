<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

final class TreatmentPlanTransitionRules
{
    public static function assertCanApprove(TreatmentPlanStatus $status): void
    {
        if ($status !== TreatmentPlanStatus::DRAFT) {
            throw new InvalidTreatmentPlanTransition('Only draft treatment plans may be approved.');
        }
    }

    public static function assertCanFinish(TreatmentPlanStatus $status): void
    {
        if (! in_array($status, [TreatmentPlanStatus::ACTIVE, TreatmentPlanStatus::PAUSED], true)) {
            throw new InvalidTreatmentPlanTransition('Only active or paused treatment plans may be finished.');
        }
    }

    public static function assertCanPause(TreatmentPlanStatus $status, string $reason): void
    {
        if ($status !== TreatmentPlanStatus::ACTIVE) {
            throw new InvalidTreatmentPlanTransition('Only active treatment plans may be paused.');
        }

        if (trim($reason) === '') {
            throw new InvalidTreatmentPlanTransition('Pausing a treatment plan requires a reason.');
        }
    }

    public static function assertCanReject(TreatmentPlanStatus $status, string $reason): void
    {
        if (! in_array($status, [TreatmentPlanStatus::DRAFT, TreatmentPlanStatus::APPROVED], true)) {
            throw new InvalidTreatmentPlanTransition('Only draft or approved treatment plans may be rejected.');
        }

        if (trim($reason) === '') {
            throw new InvalidTreatmentPlanTransition('Rejecting a treatment plan requires a reason.');
        }
    }

    public static function assertCanResume(TreatmentPlanStatus $status): void
    {
        if ($status !== TreatmentPlanStatus::PAUSED) {
            throw new InvalidTreatmentPlanTransition('Only paused treatment plans may be resumed.');
        }
    }

    public static function assertCanStart(TreatmentPlanStatus $status): void
    {
        if ($status !== TreatmentPlanStatus::APPROVED) {
            throw new InvalidTreatmentPlanTransition('Only approved treatment plans may be started.');
        }
    }
}
