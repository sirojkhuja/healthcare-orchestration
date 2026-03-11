<?php

namespace App\Modules\Billing\Infrastructure\Integrations;

use App\Modules\Billing\Application\Contracts\PaymentGateway;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UzumPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $providerKey = 'uzum',
        private readonly string $serviceId = '',
        private readonly string $merchantLogin = '',
        private readonly string $merchantPassword = '',
        private readonly int $confirmationTimeoutMinutes = 30,
        private readonly bool $supportsRefunds = false,
    ) {}

    #[\Override]
    public function cancelPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Uzum payments are synchronized through the Uzum Merchant API webhook flow and cannot be canceled from this route in this phase.');
    }

    public function configuredServiceId(): string
    {
        return trim($this->serviceId);
    }

    public function confirmationTimeoutMinutes(): int
    {
        return max(1, $this->confirmationTimeoutMinutes);
    }

    #[\Override]
    public function capturePayment(PaymentData $payment): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Uzum payments are confirmed through the Uzum Merchant API webhook flow and cannot be captured from this route in this phase.');
    }

    #[\Override]
    public function fetchPaymentStatus(PaymentData $payment): PaymentGatewaySnapshotData
    {
        if (
            $payment->status === 'pending'
            && $payment->pendingAt instanceof CarbonImmutable
            && $payment->pendingAt->addMinutes($this->confirmationTimeoutMinutes())->lessThanOrEqualTo(CarbonImmutable::now())
        ) {
            return new PaymentGatewaySnapshotData(
                status: 'failed',
                providerPaymentId: $payment->providerPaymentId,
                providerStatus: 'FAILED',
                failureCode: 'uzum_timeout',
                failureMessage: sprintf(
                    'Uzum payment was not confirmed within %d minutes.',
                    $this->confirmationTimeoutMinutes(),
                ),
                occurredAt: $payment->pendingAt->addMinutes($this->confirmationTimeoutMinutes()),
            );
        }

        return new PaymentGatewaySnapshotData(
            status: $payment->status,
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: $payment->providerStatus ?? ($payment->status === 'initiated' ? 'awaiting_uzum_webhook' : null),
            failureCode: $payment->failureCode,
            failureMessage: $payment->failureMessage,
            reason: $payment->refundReason ?? $payment->cancelReason,
        );
    }

    #[\Override]
    public function initiatePayment(PaymentGatewayInitiationRequestData $request): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: 'initiated',
            providerStatus: 'awaiting_uzum_webhook',
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
        throw new ConflictHttpException('Uzum reversals are synchronized through the Uzum Merchant API webhook flow and cannot be refunded from this route in this phase.');
    }

    #[\Override]
    public function supportsRefunds(): bool
    {
        return $this->supportsRefunds;
    }

    #[\Override]
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $trimmed = trim($signature);

        if (! preg_match('/^Basic\s+(.+)$/i', $trimmed, $matches)) {
            return false;
        }

        $decoded = base64_decode(trim($matches[1]), true);

        if (! is_string($decoded) || $decoded === '') {
            return false;
        }

        return hash_equals(
            sprintf('%s:%s', $this->merchantLogin, $this->merchantPassword),
            $decoded,
        );
    }
}
