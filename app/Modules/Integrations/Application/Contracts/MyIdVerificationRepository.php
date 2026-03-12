<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\MyIdVerificationData;
use Carbon\CarbonImmutable;

interface MyIdVerificationRepository
{
    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $metadata
     */
    public function create(
        string $tenantId,
        string $webhookId,
        string $externalReference,
        string $providerReference,
        array $subject,
        array $metadata,
        CarbonImmutable $now,
    ): MyIdVerificationData;

    public function findByProviderReference(string $tenantId, string $providerReference): ?MyIdVerificationData;

    /**
     * @param  array<string, mixed>  $resultPayload
     */
    public function complete(
        string $tenantId,
        string $providerReference,
        string $status,
        array $resultPayload,
        CarbonImmutable $completedAt,
        CarbonImmutable $updatedAt,
    ): ?MyIdVerificationData;
}
