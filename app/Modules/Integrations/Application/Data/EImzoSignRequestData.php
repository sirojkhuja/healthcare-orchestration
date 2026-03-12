<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EImzoSignRequestData
{
    /**
     * @param  array<string, mixed>  $signer
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $signaturePayload
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $webhookId,
        public string $externalReference,
        public string $providerReference,
        public string $status,
        public string $documentHash,
        public string $documentName,
        public array $signer,
        public array $metadata,
        public array $signaturePayload,
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
            'sign_request_id' => $this->id,
            'integration_key' => 'eimzo',
            'external_reference' => $this->externalReference,
            'provider_reference' => $this->providerReference,
            'status' => $this->status,
            'document_hash' => $this->documentHash,
            'document_name' => $this->documentName,
            'signer' => $this->signer,
            'metadata' => $this->metadata,
            'signature_payload' => $this->signaturePayload,
            'completed_at' => $this->completedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
