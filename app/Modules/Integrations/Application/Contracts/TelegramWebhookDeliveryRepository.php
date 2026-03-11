<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\TelegramWebhookDeliveryData;
use Carbon\CarbonImmutable;

interface TelegramWebhookDeliveryRepository
{
    /**
     * @param  array{
     *     provider_key: string,
     *     update_id: string,
     *     event_type: string,
     *     chat_id: ?string,
     *     message_id: ?string,
     *     resolved_tenant_id: ?string,
     *     payload_hash: string,
     *     secret_hash: string,
     *     outcome: string,
     *     error_code: ?string,
     *     error_message: ?string,
     *     processed_at: ?CarbonImmutable,
     *     payload: array<string, mixed>,
     *     response: array<string, mixed>
     * }  $attributes
     */
    public function create(array $attributes): TelegramWebhookDeliveryData;

    public function findByUpdateId(string $providerKey, string $updateId): ?TelegramWebhookDeliveryData;
}
