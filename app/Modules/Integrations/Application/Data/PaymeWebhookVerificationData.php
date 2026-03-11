<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class PaymeWebhookVerificationData
{
    public function __construct(
        public string $providerKey,
        public bool $valid,
    ) {}

    /**
     * @return array{provider_key: string, valid: bool}
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'valid' => $this->valid,
        ];
    }
}
