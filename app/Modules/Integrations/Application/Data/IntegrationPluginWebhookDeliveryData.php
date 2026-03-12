<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationPluginWebhookDeliveryData
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        public string $id,
        public string $integrationKey,
        public string $webhookId,
        public string $resolvedTenantId,
        public string $deliveryId,
        public ?string $providerReference,
        public string $eventType,
        public string $payloadHash,
        public string $secretHash,
        public string $outcome,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $processedAt,
        public ?array $payload,
        public ?array $response,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
