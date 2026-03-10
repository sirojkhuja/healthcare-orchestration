<?php

namespace App\Modules\Lab\Domain\LabOrders;

final class LabOrderTransitionRules
{
    public static function assertCanCancel(LabOrderStatus $status, string $reason): void
    {
        self::assertReason($reason, 'Canceling a lab order requires a reason.');

        if (! in_array($status, [
            LabOrderStatus::DRAFT,
            LabOrderStatus::SENT,
            LabOrderStatus::SPECIMEN_COLLECTED,
            LabOrderStatus::SPECIMEN_RECEIVED,
        ], true)) {
            self::reject('Only draft or in-flight lab orders may be canceled.');
        }
    }

    public static function assertCanComplete(LabOrderStatus $status): void
    {
        if ($status !== LabOrderStatus::SPECIMEN_RECEIVED) {
            self::reject('Only specimen_received lab orders can be completed.');
        }
    }

    public static function assertCanMarkSpecimenCollected(LabOrderStatus $status): void
    {
        if ($status !== LabOrderStatus::SENT) {
            self::reject('Only sent lab orders can be marked as specimen_collected.');
        }
    }

    public static function assertCanMarkSpecimenReceived(LabOrderStatus $status): void
    {
        if ($status !== LabOrderStatus::SPECIMEN_COLLECTED) {
            self::reject('Only specimen_collected lab orders can be marked as specimen_received.');
        }
    }

    public static function assertCanSend(LabOrderStatus $status): void
    {
        if ($status !== LabOrderStatus::DRAFT) {
            self::reject('Only draft lab orders can be sent.');
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
        throw new InvalidLabOrderTransition($message);
    }
}
