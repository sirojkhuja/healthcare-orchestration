<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class MyIdVerificationData
{
    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $resultPayload
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $webhookId,
        public string $externalReference,
        public string $providerReference,
        public string $status,
        public array $subject,
        public array $metadata,
        public array $resultPayload,
        public ?CarbonImmutable $completedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verification_id' => $this->id,
            'integration_key' => 'myid',
            'external_reference' => $this->externalReference,
            'provider_reference' => $this->providerReference,
            'status' => $this->status,
            'subject' => $this->subject,
            'metadata' => $this->metadata,
            'result_payload' => $this->resultPayload,
            'completed_at' => $this->completedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
