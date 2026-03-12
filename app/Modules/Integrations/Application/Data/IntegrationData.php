<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class IntegrationData
{
    /**
     * @param  array<string, bool>  $capabilities
     * @param  array<string, mixed>  $credentialSummary
     */
    public function __construct(
        public string $integrationKey,
        public string $name,
        public string $category,
        public bool $enabled,
        public bool $available,
        public ?string $featureFlag,
        public array $capabilities,
        public array $credentialSummary,
        public string $healthStatus,
        public int $webhookCount,
        public int $tokenCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'integration_key' => $this->integrationKey,
            'name' => $this->name,
            'category' => $this->category,
            'enabled' => $this->enabled,
            'available' => $this->available,
            'feature_flag' => $this->featureFlag,
            'capabilities' => $this->capabilities,
            'credentials' => $this->credentialSummary,
            'health' => [
                'status' => $this->healthStatus,
            ],
            'webhooks' => [
                'count' => $this->webhookCount,
            ],
            'tokens' => [
                'count' => $this->tokenCount,
            ],
        ];
    }
}
