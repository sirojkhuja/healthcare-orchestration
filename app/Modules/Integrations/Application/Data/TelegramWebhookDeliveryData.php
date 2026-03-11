<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TelegramWebhookDeliveryData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public string $deliveryRecordId,
        public string $providerKey,
        public string $updateId,
        public string $eventType,
        public ?string $chatId,
        public ?string $messageId,
        public ?string $resolvedTenantId,
        public string $payloadHash,
        public string $secretHash,
        public string $outcome,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $processedAt,
        public array $payload,
        public array $response,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
