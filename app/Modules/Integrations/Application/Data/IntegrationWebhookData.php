<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationWebhookData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $integrationKey,
        public string $name,
        public string $endpointUrl,
        public string $authMode,
        public string $status,
        public bool $secretConfigured,
        public bool $rotateSupported,
        public ?string $secretPlaintext,
        public array $metadata,
        public ?CarbonImmutable $secretLastRotatedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'integration_key' => $this->integrationKey,
            'name' => $this->name,
            'endpoint_url' => $this->endpointUrl,
            'auth_mode' => $this->authMode,
            'status' => $this->status,
            'secret_configured' => $this->secretConfigured,
            'rotate_supported' => $this->rotateSupported,
            'metadata' => $this->metadata,
            'secret_last_rotated_at' => $this->secretLastRotatedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];

        if ($this->secretPlaintext !== null) {
            $data['secret'] = $this->secretPlaintext;
        }

        return $data;
    }
}
