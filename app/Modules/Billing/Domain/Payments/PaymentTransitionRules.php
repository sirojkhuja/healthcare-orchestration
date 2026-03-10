<?php

namespace App\Modules\Billing\Domain\Payments;

final class PaymentTransitionRules
{
    public static function assertCanCancel(PaymentStatus $status): void
    {
        if ($status !== PaymentStatus::PENDING) {
            throw new InvalidPaymentTransition('Only pending payments may be canceled.');
        }
    }

    public static function assertCanCapture(PaymentStatus $status): void
    {
        if ($status !== PaymentStatus::PENDING) {
            throw new InvalidPaymentTransition('Only pending payments may be captured.');
        }
    }

    public static function assertCanFail(PaymentStatus $status): void
    {
        if ($status !== PaymentStatus::PENDING) {
            throw new InvalidPaymentTransition('Only pending payments may fail.');
        }
    }

    public static function assertCanMarkPending(PaymentStatus $status): void
    {
        if ($status !== PaymentStatus::INITIATED) {
            throw new InvalidPaymentTransition('Only initiated payments may move to pending.');
        }
    }

    public static function assertCanRefund(PaymentStatus $status, bool $supportsRefunds): void
    {
        if (! $supportsRefunds) {
            throw new InvalidPaymentTransition('Refunds are not supported for the selected payment provider.');
        }

        if ($status !== PaymentStatus::CAPTURED) {
            throw new InvalidPaymentTransition('Only captured payments may be refunded.');
        }
    }
}
