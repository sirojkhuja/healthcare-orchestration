<?php

namespace App\Modules\Billing\Domain\Payments;

use DateTimeImmutable;

final class Payment
{
    private PaymentStatus $status;

    private ?PaymentTransitionData $lastTransition = null;

    private ?DateTimeImmutable $pendingAt = null;

    private ?DateTimeImmutable $capturedAt = null;

    private ?DateTimeImmutable $failedAt = null;

    private ?DateTimeImmutable $canceledAt = null;

    private ?DateTimeImmutable $refundedAt = null;

    private ?string $failureCode = null;

    private ?string $failureMessage = null;

    private ?string $cancelReason = null;

    private ?string $refundReason = null;

    private function __construct(
        private readonly string $paymentId,
        private readonly string $tenantId,
        private readonly DateTimeImmutable $initiatedAt,
        PaymentStatus $status,
    ) {
        $this->status = $status;
    }

    public static function reconstitute(
        string $paymentId,
        string $tenantId,
        DateTimeImmutable $initiatedAt,
        PaymentStatus $status,
        ?PaymentTransitionData $lastTransition = null,
        ?DateTimeImmutable $pendingAt = null,
        ?DateTimeImmutable $capturedAt = null,
        ?DateTimeImmutable $failedAt = null,
        ?DateTimeImmutable $canceledAt = null,
        ?DateTimeImmutable $refundedAt = null,
        ?string $failureCode = null,
        ?string $failureMessage = null,
        ?string $cancelReason = null,
        ?string $refundReason = null,
    ): self {
        $payment = new self($paymentId, $tenantId, $initiatedAt, $status);
        $payment->lastTransition = $lastTransition;
        $payment->pendingAt = $pendingAt;
        $payment->capturedAt = $capturedAt;
        $payment->failedAt = $failedAt;
        $payment->canceledAt = $canceledAt;
        $payment->refundedAt = $refundedAt;
        $payment->failureCode = $failureCode;
        $payment->failureMessage = $failureMessage;
        $payment->cancelReason = $cancelReason;
        $payment->refundReason = $refundReason;

        return $payment;
    }

    public function cancel(DateTimeImmutable $occurredAt, PaymentActor $actor, ?string $reason = null): void
    {
        PaymentTransitionRules::assertCanCancel($this->status);
        $this->canceledAt = $occurredAt;
        $this->cancelReason = self::nullableTrim($reason);
        $this->applyTransition(PaymentStatus::CANCELED, $occurredAt, $actor, $this->cancelReason);
    }

    public function capture(DateTimeImmutable $occurredAt, PaymentActor $actor): void
    {
        PaymentTransitionRules::assertCanCapture($this->status);
        $this->capturedAt = $occurredAt;
        $this->applyTransition(PaymentStatus::CAPTURED, $occurredAt, $actor);
    }

    public function fail(
        DateTimeImmutable $occurredAt,
        PaymentActor $actor,
        ?string $failureCode = null,
        ?string $failureMessage = null,
    ): void {
        PaymentTransitionRules::assertCanFail($this->status);
        $this->failedAt = $occurredAt;
        $this->failureCode = self::nullableTrim($failureCode);
        $this->failureMessage = self::nullableTrim($failureMessage);
        $this->applyTransition(PaymentStatus::FAILED, $occurredAt, $actor, $this->failureMessage);
    }

    public function markPending(DateTimeImmutable $occurredAt, PaymentActor $actor): void
    {
        PaymentTransitionRules::assertCanMarkPending($this->status);
        $this->pendingAt = $occurredAt;
        $this->applyTransition(PaymentStatus::PENDING, $occurredAt, $actor);
    }

    public function refund(
        DateTimeImmutable $occurredAt,
        PaymentActor $actor,
        bool $supportsRefunds,
        ?string $reason = null,
    ): void {
        PaymentTransitionRules::assertCanRefund($this->status, $supportsRefunds);
        $this->refundedAt = $occurredAt;
        $this->refundReason = self::nullableTrim($reason);
        $this->applyTransition(PaymentStatus::REFUNDED, $occurredAt, $actor, $this->refundReason);
    }

    /**
     * @return array{
     *     payment_id: string,
     *     tenant_id: string,
     *     status: string,
     *     last_transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null
     *     }|null,
     *     initiated_at: string,
     *     pending_at: string|null,
     *     captured_at: string|null,
     *     failed_at: string|null,
     *     canceled_at: string|null,
     *     refunded_at: string|null,
     *     failure_code: string|null,
     *     failure_message: string|null,
     *     cancel_reason: string|null,
     *     refund_reason: string|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status->value,
            'last_transition' => $this->lastTransition?->toArray(),
            'initiated_at' => $this->initiatedAt->format(DATE_ATOM),
            'pending_at' => $this->pendingAt?->format(DATE_ATOM),
            'captured_at' => $this->capturedAt?->format(DATE_ATOM),
            'failed_at' => $this->failedAt?->format(DATE_ATOM),
            'canceled_at' => $this->canceledAt?->format(DATE_ATOM),
            'refunded_at' => $this->refundedAt?->format(DATE_ATOM),
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'cancel_reason' => $this->cancelReason,
            'refund_reason' => $this->refundReason,
        ];
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    private function applyTransition(
        PaymentStatus $toStatus,
        DateTimeImmutable $occurredAt,
        PaymentActor $actor,
        ?string $reason = null,
    ): void {
        $this->lastTransition = new PaymentTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
        );
        $this->status = $toStatus;
    }

    private static function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
