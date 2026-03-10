<?php

namespace App\Modules\Lab\Application\Contracts;

use App\Modules\Lab\Application\Data\LabWebhookDeliveryData;
use Carbon\CarbonImmutable;

interface LabWebhookDeliveryRepository
{
    /**
     * @param  array{
     *     provider_key: string,
     *     delivery_id: string,
     *     payload_hash: string,
     *     signature_hash: string,
     *     lab_order_id: ?string,
     *     resolved_tenant_id: ?string,
     *     outcome: string,
     *     occurred_at: ?CarbonImmutable,
     *     processed_at: ?CarbonImmutable,
     *     error_message: ?string,
     *     payload: array<string, mixed>|null
     * }  $attributes
     */
    public function create(array $attributes): LabWebhookDeliveryData;

    public function findByProviderAndDeliveryId(string $providerKey, string $deliveryId): ?LabWebhookDeliveryData;
}
