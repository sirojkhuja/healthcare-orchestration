<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Integrations\Application\Exceptions\PaymeJsonRpcException;

final class PaymePaymentResolver
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function assertAmountMatches(PaymentData $payment, array $params): void
    {
        if ($this->requiredInt($params['amount'] ?? null, 'amount') !== $this->toMinorUnits($payment->amount)) {
            throw new PaymeJsonRpcException(-31001, 'The amount does not match the linked payment.');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paymentByAccount(array $params): PaymentData
    {
        /** @var mixed $accountCandidate */
        $accountCandidate = $params['account'] ?? null;
        $account = is_array($accountCandidate) ? $accountCandidate : null;
        $paymentId = is_array($account) ? ($account['payment_id'] ?? null) : null;

        if (! is_string($paymentId) || trim($paymentId) === '') {
            throw new PaymeJsonRpcException(-31050, 'The account.payment_id field is invalid.', 'account.payment_id');
        }

        $payment = $this->paymentRepository->find(trim($paymentId));

        if (! $payment instanceof PaymentData || $payment->providerKey !== 'payme') {
            throw new PaymeJsonRpcException(-31050, 'The account.payment_id field is invalid.', 'account.payment_id');
        }

        return $payment;
    }

    public function paymentByProviderTransactionId(string $providerTransactionId): PaymentData
    {
        $payment = $this->paymentRepository->findByProviderPaymentId('payme', $providerTransactionId);

        if (! $payment instanceof PaymentData) {
            throw new PaymeJsonRpcException(-31003, 'The transaction was not found.');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function providerTransactionId(array $params): string
    {
        return $this->requiredString($params['id'] ?? null, 'id');
    }

    public function requiredInt(mixed $value, string $field): int
    {
        if (! is_numeric($value)) {
            throw new PaymeJsonRpcException(-32602, sprintf('The %s field is required.', $field));
        }

        return (int) $value;
    }

    public function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new PaymeJsonRpcException(-32602, sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
