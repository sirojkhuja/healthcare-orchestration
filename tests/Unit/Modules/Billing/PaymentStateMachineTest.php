<?php

use App\Modules\Billing\Domain\Payments\InvalidPaymentTransition;
use App\Modules\Billing\Domain\Payments\Payment;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\Billing\Domain\Payments\PaymentStatus;

it('moves payments from initiated to captured and refunded through the documented lifecycle', function (): void {
    $actor = new PaymentActor(type: 'user', id: 'user-1', name: 'Billing Admin');
    $payment = Payment::reconstitute(
        paymentId: 'pay-1',
        tenantId: 'tenant-1',
        initiatedAt: new DateTimeImmutable('2026-03-11T11:00:00+05:00'),
        status: PaymentStatus::INITIATED,
    );

    $payment->markPending(new DateTimeImmutable('2026-03-11T11:01:00+05:00'), $actor);
    $payment->capture(new DateTimeImmutable('2026-03-11T11:03:00+05:00'), $actor);
    $payment->refund(new DateTimeImmutable('2026-03-11T11:05:00+05:00'), $actor, true, 'Duplicate payment');

    expect($payment->status())->toBe(PaymentStatus::REFUNDED);
    expect($payment->snapshot()['pending_at'])->toBe('2026-03-11T11:01:00+05:00');
    expect($payment->snapshot()['captured_at'])->toBe('2026-03-11T11:03:00+05:00');
    expect($payment->snapshot()['refunded_at'])->toBe('2026-03-11T11:05:00+05:00');
    expect($payment->snapshot()['refund_reason'])->toBe('Duplicate payment');
});

it('supports pending failure and cancel flows but rejects invalid terminal transitions', function (): void {
    $actor = new PaymentActor(type: 'user', id: 'user-2', name: 'Billing Admin');
    $failedPayment = Payment::reconstitute(
        paymentId: 'pay-2',
        tenantId: 'tenant-1',
        initiatedAt: new DateTimeImmutable('2026-03-11T12:00:00+05:00'),
        status: PaymentStatus::INITIATED,
    );

    expect(fn () => $failedPayment->capture(new DateTimeImmutable('2026-03-11T12:01:00+05:00'), $actor))
        ->toThrow(InvalidPaymentTransition::class);

    $failedPayment->markPending(new DateTimeImmutable('2026-03-11T12:02:00+05:00'), $actor);
    $failedPayment->fail(new DateTimeImmutable('2026-03-11T12:03:00+05:00'), $actor, 'provider_timeout', 'Timed out');

    expect($failedPayment->status())->toBe(PaymentStatus::FAILED);
    expect(fn () => $failedPayment->refund(new DateTimeImmutable('2026-03-11T12:04:00+05:00'), $actor, true))
        ->toThrow(InvalidPaymentTransition::class);

    $canceledPayment = Payment::reconstitute(
        paymentId: 'pay-3',
        tenantId: 'tenant-1',
        initiatedAt: new DateTimeImmutable('2026-03-11T12:10:00+05:00'),
        status: PaymentStatus::INITIATED,
    );

    $canceledPayment->markPending(new DateTimeImmutable('2026-03-11T12:11:00+05:00'), $actor);
    $canceledPayment->cancel(new DateTimeImmutable('2026-03-11T12:12:00+05:00'), $actor, 'Patient requested cancellation');

    expect($canceledPayment->status())->toBe(PaymentStatus::CANCELED);
    expect($canceledPayment->snapshot()['cancel_reason'])->toBe('Patient requested cancellation');
});

it('exposes the documented payment status catalog and refund-support guard', function (): void {
    $actor = new PaymentActor(type: 'user', id: 'user-3', name: 'Billing Admin');
    $payment = Payment::reconstitute(
        paymentId: 'pay-4',
        tenantId: 'tenant-1',
        initiatedAt: new DateTimeImmutable('2026-03-11T13:00:00+05:00'),
        status: PaymentStatus::INITIATED,
    );

    $payment->markPending(new DateTimeImmutable('2026-03-11T13:01:00+05:00'), $actor);
    $payment->capture(new DateTimeImmutable('2026-03-11T13:02:00+05:00'), $actor);

    expect(fn () => $payment->refund(new DateTimeImmutable('2026-03-11T13:03:00+05:00'), $actor, false))
        ->toThrow(InvalidPaymentTransition::class);

    expect(PaymentStatus::all())->toBe([
        'initiated',
        'pending',
        'captured',
        'failed',
        'canceled',
        'refunded',
    ]);
    expect(PaymentStatus::FAILED->isTerminal())->toBeTrue();
    expect(PaymentStatus::CAPTURED->isTerminal())->toBeFalse();
});
