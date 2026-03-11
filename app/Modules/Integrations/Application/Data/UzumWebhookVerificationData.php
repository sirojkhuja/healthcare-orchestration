<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class UzumWebhookVerificationData
{
    public function __construct(
        public string $providerKey,
        public bool $valid,
        public string $operation,
        public ?string $transactionId = null,
        public ?string $paymentId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'valid' => $this->valid,
            'operation' => $this->operation,
            'transaction_id' => $this->transactionId,
            'payment_id' => $this->paymentId,
        ];
    }
}
