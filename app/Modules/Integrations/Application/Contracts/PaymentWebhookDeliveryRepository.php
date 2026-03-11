<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use Carbon\CarbonImmutable;

interface PaymentWebhookDeliveryRepository
{
    /**
     * @param  array{
     *     provider_key: string,
     *     method: string,
     *     replay_key: ?string,
     *     provider_transaction_id: ?string,
     *     request_id: string|null,
     *     payment_id: ?string,
     *     resolved_tenant_id: ?string,
     *     payload_hash: string,
     *     auth_hash: string,
     *     provider_time_millis: ?int,
     *     outcome: string,
     *     provider_error_code: ?string,
     *     provider_error_message: ?string,
     *     processed_at: ?CarbonImmutable,
     *     payload: array<string, mixed>|null,
     *     response: array<string, mixed>|null
     * }  $attributes
     */
    public function create(array $attributes): PaymentWebhookDeliveryData;

    public function findByReplayKey(string $providerKey, string $method, string $replayKey): ?PaymentWebhookDeliveryData;

    /**
     * @return list<PaymentWebhookDeliveryData>
     */
    public function listByProviderMethodAndTimeRange(
        string $providerKey,
        string $method,
        int $fromMillis,
        int $toMillis,
    ): array;
}
