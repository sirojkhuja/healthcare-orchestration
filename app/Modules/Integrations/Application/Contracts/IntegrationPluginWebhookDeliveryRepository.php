<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\IntegrationPluginWebhookDeliveryData;
use Carbon\CarbonImmutable;

interface IntegrationPluginWebhookDeliveryRepository
{
    /**
     * @param  array{
     *   integration_key: string,
     *   webhook_id: string,
     *   resolved_tenant_id: string,
     *   delivery_id: string,
     *   provider_reference: string|null,
     *   event_type: string,
     *   payload_hash: string,
     *   secret_hash: string,
     *   outcome: string,
     *   error_code: string|null,
     *   error_message: string|null,
     *   processed_at: CarbonImmutable|null,
     *   payload: array<string, mixed>|null,
     *   response: array<string, mixed>|null
     * }  $attributes
     */
    public function create(array $attributes): IntegrationPluginWebhookDeliveryData;

    public function findByReplayKey(
        string $integrationKey,
        string $webhookId,
        string $deliveryId,
    ): ?IntegrationPluginWebhookDeliveryData;
}
