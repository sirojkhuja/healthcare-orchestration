<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use Carbon\CarbonImmutable;

final class PaymeTransactionViewBuilder
{
    /**
     * @return array{create_time: int, transaction: string, state: int}
     */
    public function buildCreateResult(PaymentData $payment): array
    {
        return [
            'create_time' => $this->millis($payment->pendingAt ?? $payment->updatedAt),
            'transaction' => $payment->paymentId,
            'state' => $this->state($payment),
        ];
    }

    /**
     * @return array{perform_time: int, transaction: string, state: int}
     */
    public function buildPerformResult(PaymentData $payment): array
    {
        return [
            'perform_time' => $this->millis($payment->capturedAt ?? $payment->updatedAt),
            'transaction' => $payment->paymentId,
            'state' => $this->state($payment),
        ];
    }

    /**
     * @return array{cancel_time: int, transaction: string, state: int}
     */
    public function buildCancelResult(PaymentData $payment): array
    {
        $cancelAt = $payment->refundedAt ?? $payment->canceledAt ?? $payment->updatedAt;

        return [
            'cancel_time' => $this->millis($cancelAt),
            'transaction' => $payment->paymentId,
            'state' => $this->state($payment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCheckResult(
        PaymentData $payment,
        PaymentWebhookDeliveryData $createDelivery,
        ?PaymentWebhookDeliveryData $cancelDelivery,
    ): array {
        return [
            'create_time' => $this->millis($payment->pendingAt ?? $createDelivery->processedAt ?? $payment->updatedAt),
            'perform_time' => $payment->capturedAt !== null ? $this->millis($payment->capturedAt) : 0,
            'cancel_time' => $this->cancelTime($payment),
            'transaction' => $payment->paymentId,
            'state' => $this->state($payment),
            'reason' => $this->reason($cancelDelivery),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatementTransaction(
        PaymentData $payment,
        PaymentWebhookDeliveryData $createDelivery,
        ?PaymentWebhookDeliveryData $cancelDelivery,
    ): array {
        return [
            'id' => $createDelivery->providerTransactionId,
            'time' => $createDelivery->providerTimeMillis ?? 0,
            'amount' => $this->requestAmount($createDelivery),
            'account' => $this->requestAccount($createDelivery),
            'create_time' => $this->millis($payment->pendingAt ?? $createDelivery->processedAt ?? $payment->updatedAt),
            'perform_time' => $payment->capturedAt !== null ? $this->millis($payment->capturedAt) : 0,
            'cancel_time' => $this->cancelTime($payment),
            'transaction' => $payment->paymentId,
            'state' => $this->state($payment),
            'reason' => $this->reason($cancelDelivery),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestAccount(PaymentWebhookDeliveryData $delivery): array
    {
        $params = $this->requestParams($delivery);
        /** @var mixed $accountCandidate */
        $accountCandidate = $params['account'] ?? null;
        $account = $accountCandidate;

        if (! is_array($account)) {
            return [];
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($account as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function requestAmount(PaymentWebhookDeliveryData $delivery): int
    {
        $params = $this->requestParams($delivery);
        /** @var mixed $amount */
        $amount = $params['amount'] ?? null;

        return is_numeric($amount) ? (int) $amount : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestParams(PaymentWebhookDeliveryData $delivery): array
    {
        $payload = $delivery->payload;
        /** @var mixed $params */
        $params = is_array($payload) ? ($payload['params'] ?? null) : null;

        if (! is_array($params)) {
            return [];
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function reason(?PaymentWebhookDeliveryData $cancelDelivery): ?int
    {
        if (! $cancelDelivery instanceof PaymentWebhookDeliveryData) {
            return null;
        }

        $params = $this->requestParams($cancelDelivery);
        /** @var mixed $reason */
        $reason = $params['reason'] ?? null;

        return is_numeric($reason) ? (int) $reason : null;
    }

    private function cancelTime(PaymentData $payment): int
    {
        $value = $payment->refundedAt ?? $payment->canceledAt;

        return $value instanceof CarbonImmutable ? $this->millis($value) : 0;
    }

    private function millis(CarbonImmutable $value): int
    {
        return $value->getTimestampMs();
    }

    private function state(PaymentData $payment): int
    {
        return match ($payment->status) {
            'pending' => 1,
            'captured' => 2,
            'canceled' => -1,
            'refunded' => -2,
            default => 0,
        };
    }
}
