<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentWebhookDeliveryData
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        public string $deliveryRecordId,
        public string $providerKey,
        public string $method,
        public ?string $replayKey,
        public ?string $providerTransactionId,
        public ?string $requestId,
        public ?string $paymentId,
        public ?string $resolvedTenantId,
        public string $payloadHash,
        public string $authHash,
        public ?int $providerTimeMillis,
        public string $outcome,
        public ?string $providerErrorCode,
        public ?string $providerErrorMessage,
        public ?CarbonImmutable $processedAt,
        public ?array $payload,
        public ?array $response,
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
            'method' => $this->method,
            'replay_key' => $this->replayKey,
            'provider_transaction_id' => $this->providerTransactionId,
            'request_id' => $this->requestId,
            'payment_id' => $this->paymentId,
            'resolved_tenant_id' => $this->resolvedTenantId,
            'payload_hash' => $this->payloadHash,
            'auth_hash' => $this->authHash,
            'provider_time_millis' => $this->providerTimeMillis,
            'outcome' => $this->outcome,
            'provider_error_code' => $this->providerErrorCode,
            'provider_error_message' => $this->providerErrorMessage,
            'processed_at' => $this->processedAt?->toIso8601String(),
            'payload' => $this->payload,
            'response' => $this->response,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
