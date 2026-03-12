<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\InboundIntegrationWebhookData;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use Carbon\CarbonImmutable;

interface IntegrationWebhookRepository
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function create(
        string $tenantId,
        string $integrationKey,
        string $name,
        string $endpointUrl,
        string $authMode,
        ?string $secret,
        ?string $secretHash,
        ?CarbonImmutable $secretLastRotatedAt,
        string $status,
        array $metadata,
        CarbonImmutable $now,
    ): IntegrationWebhookData;

    public function delete(string $tenantId, string $integrationKey, string $webhookId): bool;

    public function findInboundTarget(string $integrationKey, string $webhookId): ?InboundIntegrationWebhookData;

    public function findInTenant(string $tenantId, string $integrationKey, string $webhookId): ?IntegrationWebhookData;

    /**
     * @return list<IntegrationWebhookData>
     */
    public function list(string $tenantId, string $integrationKey): array;

    public function updateSecret(
        string $tenantId,
        string $integrationKey,
        string $webhookId,
        ?string $secret,
        ?string $secretHash,
        ?CarbonImmutable $secretLastRotatedAt,
        CarbonImmutable $updatedAt,
    ): ?IntegrationWebhookData;
}
