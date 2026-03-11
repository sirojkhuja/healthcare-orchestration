<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class ClickWebhookVerificationData
{
    public function __construct(
        public string $providerKey,
        public bool $valid,
        public ?int $action = null,
        public ?string $merchantTransactionId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'valid' => $this->valid,
            'action' => $this->action,
            'merchant_trans_id' => $this->merchantTransactionId,
        ];
    }
}
