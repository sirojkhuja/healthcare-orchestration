<?php

namespace App\Modules\Pharmacy\Domain\Prescriptions;

final class PrescriptionTransitionRules
{
    public static function assertCanCancel(PrescriptionStatus $status, string $reason): void
    {
        self::assertReason($reason, 'Canceling a prescription requires a reason.');

        if (! in_array($status, [PrescriptionStatus::DRAFT, PrescriptionStatus::ISSUED], true)) {
            self::reject('Only draft or issued prescriptions may be canceled.');
        }
    }

    public static function assertCanDispense(PrescriptionStatus $status): void
    {
        if ($status !== PrescriptionStatus::ISSUED) {
            self::reject('Only issued prescriptions can be dispensed.');
        }
    }

    public static function assertCanIssue(PrescriptionStatus $status): void
    {
        if ($status !== PrescriptionStatus::DRAFT) {
            self::reject('Only draft prescriptions can be issued.');
        }
    }

    private static function assertReason(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            self::reject($message);
        }
    }

    private static function reject(string $message): never
    {
        throw new InvalidPrescriptionTransition($message);
    }
}
