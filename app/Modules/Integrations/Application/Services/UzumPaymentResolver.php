<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Integrations\Application\Exceptions\UzumWebhookException;
use Carbon\CarbonImmutable;
use Throwable;

final class UzumPaymentResolver
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertAmountMatches(PaymentData $payment, array $payload): void
    {
        if ($this->normalizeAmount($this->requiredAmount($payload)) !== $this->normalizeAmount($payment->amount)) {
            throw new UzumWebhookException('AMOUNT_MISMATCH', 'The amount does not match the linked payment.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentByPayload(array $payload): PaymentData
    {
        $paymentId = $this->paymentId($payload);
        $payment = $this->paymentRepository->find($paymentId);

        if (! $payment instanceof PaymentData || $payment->providerKey !== 'uzum') {
            throw new UzumWebhookException('PAYMENT_NOT_FOUND', 'The linked Uzum payment could not be found.');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentByTransactionOrPayload(array $payload): PaymentData
    {
        $transId = $this->transactionId($payload);
        $payment = $this->paymentRepository->findByProviderPaymentId('uzum', $transId);

        if ($payment instanceof PaymentData) {
            return $payment;
        }

        return $this->paymentByPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentId(array $payload): string
    {
        /** @var mixed $paramsCandidate */
        $paramsCandidate = $payload['params'] ?? null;
        $params = is_array($paramsCandidate) ? $paramsCandidate : [];
        /** @var mixed $accountCandidate */
        $accountCandidate = $params['account'] ?? null;
        $account = is_array($accountCandidate) ? $accountCandidate : [];
        /** @var mixed $paymentId */
        $paymentId = $params['payment_id'] ?? ($account['value'] ?? null);

        if (! is_string($paymentId) && ! is_int($paymentId) && ! is_float($paymentId)) {
            throw new UzumWebhookException('INVALID_REQUEST', 'The params.payment_id field is required.');
        }

        $normalized = trim((string) $paymentId);

        if ($normalized === '') {
            throw new UzumWebhookException('INVALID_REQUEST', 'The params.payment_id field is required.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerTimeMillis(array $payload): ?int
    {
        /** @psalm-suppress MixedAssignment */
        $timestamp = $payload['timestamp'] ?? null;

        if (is_numeric($timestamp)) {
            return (int) $timestamp;
        }

        if (is_string($timestamp) && trim($timestamp) !== '') {
            try {
                return CarbonImmutable::parse(trim($timestamp))->getTimestampMs();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public function requestedOperation(string $operation): string
    {
        $normalized = strtolower(trim($operation));

        if (! in_array($normalized, ['check', 'create', 'confirm', 'reverse', 'status'], true)) {
            throw new UzumWebhookException('INVALID_OPERATION', 'The Uzum operation is invalid.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requiredAmount(array $payload): string
    {
        /** @psalm-suppress MixedAssignment */
        $amount = $payload['amount'] ?? null;

        if (is_int($amount) || is_float($amount)) {
            return (string) $amount;
        }

        if (is_string($amount) && trim($amount) !== '') {
            return trim($amount);
        }

        throw new UzumWebhookException('INVALID_REQUEST', 'The amount field is required.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function serviceId(array $payload): string
    {
        /** @psalm-suppress MixedAssignment */
        $serviceId = $payload['serviceId'] ?? null;

        if (! is_string($serviceId) && ! is_int($serviceId) && ! is_float($serviceId)) {
            throw new UzumWebhookException('INVALID_REQUEST', 'The serviceId field is required.');
        }

        $normalized = trim((string) $serviceId);

        if ($normalized === '') {
            throw new UzumWebhookException('INVALID_REQUEST', 'The serviceId field is required.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function transactionId(array $payload): string
    {
        /** @psalm-suppress MixedAssignment */
        $transactionId = $payload['transId'] ?? null;

        if (! is_string($transactionId) && ! is_int($transactionId) && ! is_float($transactionId)) {
            throw new UzumWebhookException('INVALID_REQUEST', 'The transId field is required.');
        }

        $normalized = trim((string) $transactionId);

        if ($normalized === '') {
            throw new UzumWebhookException('INVALID_REQUEST', 'The transId field is required.');
        }

        return $normalized;
    }

    private function normalizeAmount(string $amount): string
    {
        $numeric = (float) $amount;

        return number_format($numeric, 2, '.', '');
    }
}
