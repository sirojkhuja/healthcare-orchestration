<?php

namespace App\Modules\Lab\Application\Data;

final readonly class LabWebhookVerificationData
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
