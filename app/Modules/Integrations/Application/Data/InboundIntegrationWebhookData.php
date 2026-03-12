<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class InboundIntegrationWebhookData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $integrationKey,
        public string $name,
        public string $status,
        public ?string $secretHash,
        public array $metadata,
    ) {}
}
