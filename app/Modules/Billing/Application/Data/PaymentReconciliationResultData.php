<?php

namespace App\Modules\Billing\Application\Data;

final readonly class PaymentReconciliationResultData
{
    /**
     * @param  array<string, mixed>  $payment
     */
    public function __construct(
        public string $paymentId,
        public string $statusBefore,
        public string $statusAfter,
        public bool $changed,
        public ?string $providerPaymentId,
        public ?string $providerStatus,
        public ?string $failureCode,
        public ?string $failureMessage,
        public array $payment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'status_before' => $this->statusBefore,
            'status_after' => $this->statusAfter,
            'changed' => $this->changed,
            'provider_payment_id' => $this->providerPaymentId,
            'provider_status' => $this->providerStatus,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'payment' => $this->payment,
        ];
    }
}
