<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PaymentSnapshotSynchronizationService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function synchronize(
        PaymentData $payment,
        PaymentGatewaySnapshotData $snapshot,
        bool $supportsRefunds,
        ?string $reason = null,
        ?PaymentActor $actor = null,
        ?CarbonImmutable $occurredAt = null,
        array $metadata = [],
        ?string $tenantId = null,
    ): PaymentData {
        if ($snapshot->status === 'initiated' || $snapshot->status === $payment->status) {
            return $this->persistSnapshot(
                payment: $payment,
                snapshot: $snapshot,
                reason: $reason,
                tenantId: $tenantId,
            );
        }

        return match ($snapshot->status) {
            'pending' => $this->paymentWorkflowService->markPending(
                paymentId: $payment->paymentId,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                checkoutUrl: $snapshot->checkoutUrl,
                actor: $actor,
                occurredAt: $snapshot->occurredAt ?? $occurredAt,
                metadata: $metadata,
                tenantId: $tenantId ?? $payment->tenantId,
            ),
            'captured' => $this->paymentWorkflowService->capture(
                paymentId: $payment->paymentId,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                actor: $actor,
                occurredAt: $snapshot->occurredAt ?? $occurredAt,
                metadata: $metadata,
                tenantId: $tenantId ?? $payment->tenantId,
            ),
            'failed' => $this->paymentWorkflowService->fail(
                paymentId: $payment->paymentId,
                failureCode: $snapshot->failureCode,
                failureMessage: $snapshot->failureMessage,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                actor: $actor,
                occurredAt: $snapshot->occurredAt ?? $occurredAt,
                metadata: $metadata,
                tenantId: $tenantId ?? $payment->tenantId,
            ),
            'canceled' => $this->paymentWorkflowService->cancel(
                paymentId: $payment->paymentId,
                reason: $snapshot->reason ?? $reason,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                actor: $actor,
                occurredAt: $snapshot->occurredAt ?? $occurredAt,
                metadata: $metadata,
                tenantId: $tenantId ?? $payment->tenantId,
            ),
            'refunded' => $this->paymentWorkflowService->refund(
                paymentId: $payment->paymentId,
                supportsRefunds: $supportsRefunds,
                reason: $snapshot->reason ?? $reason,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                actor: $actor,
                occurredAt: $snapshot->occurredAt ?? $occurredAt,
                metadata: $metadata,
                tenantId: $tenantId ?? $payment->tenantId,
            ),
            default => throw new ConflictHttpException('The payment gateway returned an unsupported payment status.'),
        };
    }

    private function persistSnapshot(
        PaymentData $payment,
        PaymentGatewaySnapshotData $snapshot,
        ?string $reason = null,
        ?string $tenantId = null,
    ): PaymentData {
        $updates = array_filter(
            [
                'provider_payment_id' => $snapshot->providerPaymentId,
                'provider_status' => $snapshot->providerStatus,
                'checkout_url' => $snapshot->checkoutUrl,
                'failure_code' => $snapshot->failureCode,
                'failure_message' => $snapshot->failureMessage,
                'cancel_reason' => $snapshot->status === 'canceled' ? ($snapshot->reason ?? $reason) : null,
                'refund_reason' => $snapshot->status === 'refunded' ? ($snapshot->reason ?? $reason) : null,
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        return $this->paymentRepository->update(
            $tenantId ?? $payment->tenantId,
            $payment->paymentId,
            $updates,
        ) ?? $payment;
    }
}
