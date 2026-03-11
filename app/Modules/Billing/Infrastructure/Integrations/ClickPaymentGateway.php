<?php

namespace App\Modules\Billing\Infrastructure\Integrations;

use App\Modules\Billing\Application\Contracts\PaymentGateway;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ClickPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $providerKey = 'click',
        private readonly string $merchantId = '',
        private readonly string $serviceId = '',
        private readonly ?string $merchantUserId = null,
        private readonly string $secretKey = '',
        private readonly string $paymentBaseUrl = 'https://my.click.uz/services/pay',
        private readonly ?string $returnUrl = null,
        private readonly ?string $cardType = null,
        private readonly bool $supportsRefunds = false,
    ) {}

    #[\Override]
    public function cancelPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Click payments are completed through the Click Shop API callback flow and cannot be canceled from this route in this phase.');
    }

    public function configuredServiceId(): string
    {
        return trim($this->serviceId);
    }

    #[\Override]
    public function capturePayment(PaymentData $payment): PaymentGatewaySnapshotData
    {
        throw new ConflictHttpException('Click payments are captured through the Click Shop API callback flow and cannot be captured from this route in this phase.');
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
        throw new ConflictHttpException('Click refunds are not supported from this route in this phase.');
    }

    #[\Override]
    public function supportsRefunds(): bool
    {
        return $this->supportsRefunds;
    }

    #[\Override]
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        /** @var mixed $decoded */
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return false;
        }

        $action = $decoded['action'] ?? null;

        if (! is_numeric($action)) {
            return false;
        }

        $merchantPrepareId = (int) $action === 1
            ? $this->stringValue($decoded['merchant_prepare_id'] ?? null)
            : '';

        $expected = md5(
            $this->stringValue($decoded['click_trans_id'] ?? null)
            .$this->stringValue($decoded['service_id'] ?? null)
            .$this->secretKey
            .$this->stringValue($decoded['merchant_trans_id'] ?? null)
            .$merchantPrepareId
            .$this->stringValue($decoded['amount'] ?? null)
            .$this->stringValue($decoded['action'] ?? null)
            .$this->stringValue($decoded['sign_time'] ?? null),
        );

        return hash_equals(strtolower($expected), strtolower(trim($signature)));
    }

    private function checkoutUrl(PaymentGatewayInitiationRequestData $request): string
    {
        $params = [
            'service_id' => trim($this->serviceId),
            'merchant_id' => trim($this->merchantId),
            'amount' => $this->formatAmount($request->amount),
            'transaction_param' => $request->paymentId,
        ];

        if (is_string($this->merchantUserId) && trim($this->merchantUserId) !== '') {
            $params['merchant_user_id'] = trim($this->merchantUserId);
        }

        if (is_string($this->returnUrl) && trim($this->returnUrl) !== '') {
            $params['return_url'] = trim($this->returnUrl);
        }

        if (is_string($this->cardType) && trim($this->cardType) !== '') {
            $params['card_type'] = trim($this->cardType);
        }

        return rtrim($this->paymentBaseUrl, '?').'?'.http_build_query($params);
    }

    private function formatAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }
}
