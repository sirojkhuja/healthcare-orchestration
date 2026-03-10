<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentData
{
    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $paymentId,
        public string $tenantId,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $providerKey,
        public string $amount,
        public string $currency,
        public ?string $description,
        public string $status,
        public ?string $providerPaymentId,
        public ?string $providerStatus,
        public ?string $checkoutUrl,
        public ?string $failureCode,
        public ?string $failureMessage,
        public ?string $cancelReason,
        public ?string $refundReason,
        public ?array $lastTransition,
        public CarbonImmutable $initiatedAt,
        public ?CarbonImmutable $pendingAt,
        public ?CarbonImmutable $capturedAt,
        public ?CarbonImmutable $failedAt,
        public ?CarbonImmutable $canceledAt,
        public ?CarbonImmutable $refundedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
            'invoice' => [
                'id' => $this->invoiceId,
                'number' => $this->invoiceNumber,
            ],
            'provider' => [
                'key' => $this->providerKey,
                'payment_id' => $this->providerPaymentId,
                'status' => $this->providerStatus,
                'checkout_url' => $this->checkoutUrl,
            ],
            'amount' => [
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
            'description' => $this->description,
            'status' => $this->status,
            'failure' => $this->failureCode === null && $this->failureMessage === null ? null : [
                'code' => $this->failureCode,
                'message' => $this->failureMessage,
            ],
            'cancel_reason' => $this->cancelReason,
            'refund_reason' => $this->refundReason,
            'last_transition' => $this->lastTransition,
            'initiated_at' => $this->initiatedAt->toIso8601String(),
            'pending_at' => $this->pendingAt?->toIso8601String(),
            'captured_at' => $this->capturedAt?->toIso8601String(),
            'failed_at' => $this->failedAt?->toIso8601String(),
            'canceled_at' => $this->canceledAt?->toIso8601String(),
            'refunded_at' => $this->refundedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
