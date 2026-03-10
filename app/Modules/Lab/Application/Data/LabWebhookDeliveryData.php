<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabWebhookDeliveryData
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $deliveryRecordId,
        public string $providerKey,
        public string $deliveryId,
        public string $payloadHash,
        public string $signatureHash,
        public ?string $labOrderId,
        public ?string $resolvedTenantId,
        public string $outcome,
        public ?CarbonImmutable $occurredAt,
        public ?CarbonImmutable $processedAt,
        public ?string $errorMessage,
        public ?array $payload,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->deliveryRecordId,
            'provider_key' => $this->providerKey,
            'delivery_id' => $this->deliveryId,
            'payload_hash' => $this->payloadHash,
            'signature_hash' => $this->signatureHash,
            'lab_order_id' => $this->labOrderId,
            'resolved_tenant_id' => $this->resolvedTenantId,
            'outcome' => $this->outcome,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
            'processed_at' => $this->processedAt?->toIso8601String(),
            'error_message' => $this->errorMessage,
            'payload' => $this->payload,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
