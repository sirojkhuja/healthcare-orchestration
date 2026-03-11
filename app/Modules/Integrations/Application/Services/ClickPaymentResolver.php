<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use App\Modules\Integrations\Application\Exceptions\ClickWebhookException;
use Carbon\CarbonImmutable;

final class ClickPaymentResolver
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentWebhookDeliveryRepository $paymentWebhookDeliveryRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertAmountMatches(PaymentData $payment, array $payload): void
    {
        if ($this->toMinorUnits($payment->amount) !== $this->toMinorUnits($this->requiredString($payload, 'amount'))) {
            throw new ClickWebhookException(-2, 'Incorrect parameter amount');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentByMerchantTransactionId(array $payload): PaymentData
    {
        $merchantTransactionId = $this->requiredString($payload, 'merchant_trans_id');
        $payment = $this->paymentRepository->find($merchantTransactionId);

        if (! $payment instanceof PaymentData || $payment->providerKey !== 'click') {
            throw new ClickWebhookException(-5, 'User does not exist');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentByPrepareReference(array $payload): PaymentData
    {
        $providerTransactionId = $this->providerTransactionId($payload);
        $merchantPrepareId = $this->requiredString($payload, 'merchant_prepare_id');

        if ($merchantPrepareId !== $providerTransactionId) {
            throw new ClickWebhookException(-6, 'Transaction does not exist');
        }

        $delivery = $this->paymentWebhookDeliveryRepository->findByReplayKey('click', 'prepare', $providerTransactionId);

        if (! $delivery instanceof PaymentWebhookDeliveryData || $delivery->paymentId === null) {
            throw new ClickWebhookException(-6, 'Transaction does not exist');
        }

        $payment = $this->paymentRepository->find($delivery->paymentId);

        if (! $payment instanceof PaymentData || $payment->providerKey !== 'click') {
            throw new ClickWebhookException(-6, 'Transaction does not exist');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerPaymentId(array $payload): string
    {
        return $this->requiredString($payload, 'click_paydoc_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerTimeMillis(array $payload): ?int
    {
        $signTime = $this->requiredString($payload, 'sign_time');
        $parsed = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $signTime);

        if (! $parsed instanceof CarbonImmutable) {
            return null;
        }

        return $parsed->getTimestamp() * 1000;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerTransactionId(array $payload): string
    {
        return $this->requiredString($payload, 'click_trans_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requestedAction(array $payload): int
    {
        $action = $payload['action'] ?? null;

        if (! is_numeric($action)) {
            throw new ClickWebhookException(-8, 'Error in request from click');
        }

        $normalized = (int) $action;

        if (! in_array($normalized, [0, 1], true)) {
            throw new ClickWebhookException(-3, 'Action not found');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requiredString(array $payload, string $field): string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$field] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new ClickWebhookException(-8, 'Error in request from click');
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
