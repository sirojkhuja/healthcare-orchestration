<?php

namespace App\Modules\Billing\Infrastructure\Integrations;

use App\Modules\Billing\Application\Contracts\PaymentGateway;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PaymePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $providerKey = 'payme',
        private readonly string $merchantId = '',
        private readonly string $merchantKey = '',
        private readonly string $merchantLogin = 'Paycom',
        private readonly string $checkoutBaseUrl = 'https://checkout.paycom.uz',
        private readonly string $checkoutLanguage = 'uz',
        private readonly ?string $callback = null,
        private readonly ?int $callbackTimeout = null,
        private readonly ?string $currency = null,
        private readonly bool $supportsRefunds = true,
    ) {}

    #[\Override]
    public function cancelPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Payme payments are canceled through the Payme callback flow and cannot be canceled from this route.');
    }

    #[\Override]
    public function capturePayment(PaymentData $payment): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Payme payments are captured through the Payme callback flow and cannot be captured from this route.');
    }

    #[\Override]
    public function fetchPaymentStatus(PaymentData $payment): PaymentGatewaySnapshotData
    {
        return new PaymentGatewaySnapshotData(
            status: $payment->status,
            providerPaymentId: $payment->providerPaymentId,
            providerStatus: $payment->providerStatus ?? ($payment->status === 'initiated' ? 'checkout_pending' : null),
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
            status: 'initiated',
            providerStatus: 'checkout_pending',
            checkoutUrl: $this->checkoutUrl($request),
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
        throw new ConflictHttpException('Payme refunds are synchronized through the Payme callback flow and cannot be refunded from this route.');
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

        return hash_equals(sprintf('%s:%s', $this->merchantLogin, $this->merchantKey), $decoded);
    }

    private function checkoutUrl(PaymentGatewayInitiationRequestData $request): string
    {
        $params = [
            'm' => $this->merchantId,
            'ac.payment_id' => $request->paymentId,
            'a' => (string) $this->toMinorUnits($request->amount),
            'l' => $this->checkoutLanguage,
        ];

        if (is_string($this->callback) && trim($this->callback) !== '') {
            $params['c'] = trim($this->callback);
        }

        if (is_int($this->callbackTimeout) && $this->callbackTimeout > 0) {
            $params['ct'] = (string) $this->callbackTimeout;
        }

        if (is_string($this->currency) && trim($this->currency) !== '') {
            $params['cr'] = strtoupper(trim($this->currency));
        }

        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $encoded = base64_encode(implode(';', $pairs));

        return rtrim($this->checkoutBaseUrl, '/').'/'.$encoded;
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
