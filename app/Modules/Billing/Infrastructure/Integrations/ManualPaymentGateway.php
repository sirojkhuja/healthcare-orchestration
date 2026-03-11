<?php

namespace App\Modules\Billing\Infrastructure\Integrations;

use App\Modules\Billing\Application\Contracts\PaymentGateway;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;

final class ManualPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $providerKey = 'manual',
        private readonly bool $supportsRefunds = true,
    ) {}

    #[\Override]
    public function cancelPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: 'canceled',
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: 'canceled',
            reason: $reason,
        );
    }

    #[\Override]
    public function capturePayment(PaymentData $payment): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: 'captured',
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: 'captured',
        );
    }

    #[\Override]
    public function fetchPaymentStatus(PaymentData $payment): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: $payment->status,
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: $payment->providerStatus ?? $payment->status,
            checkoutUrl: $payment->checkoutUrl,
            failureCode: $payment->failureCode,
            failureMessage: $payment->failureMessage,
            reason: $payment->refundReason ?? $payment->cancelReason,
        );
    }

    #[\Override]
    public function initiatePayment(PaymentGatewayInitiationRequestData $request): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: 'pending',
            providerPaymentId: sprintf('%s-%s', $this->providerKey, $request->paymentId),
            providerStatus: 'pending',
        );
    }

    #[\Override]
    public function providerKey(): string
    {
        return $this->providerKey;
    }

    #[\Override]
    public function refundPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: 'refunded',
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: 'refunded',
            reason: $reason,
        );
    }

    #[\Override]
    public function supportsRefunds(): bool
    {
        return $this->supportsRefunds;
    }

    #[\Override]
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        return false;
    }
}
