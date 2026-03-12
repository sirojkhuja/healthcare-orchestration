<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\EImzoSignRequestData;
use Carbon\CarbonImmutable;

interface EImzoSignRequestRepository
{
    /**
     * @param  array<string, mixed>  $signer
     * @param  array<string, mixed>  $metadata
     */
    public function create(
        string $tenantId,
        string $webhookId,
        string $externalReference,
        string $providerReference,
        string $documentHash,
        string $documentName,
        array $signer,
        array $metadata,
        CarbonImmutable $now,
    ): EImzoSignRequestData;

    public function findByProviderReference(string $tenantId, string $providerReference): ?EImzoSignRequestData;

    /**
     * @param  array<string, mixed>  $signaturePayload
     */
    public function complete(
        string $tenantId,
        string $providerReference,
        string $status,
        array $signaturePayload,
        CarbonImmutable $completedAt,
        CarbonImmutable $updatedAt,
    ): ?EImzoSignRequestData;
}
