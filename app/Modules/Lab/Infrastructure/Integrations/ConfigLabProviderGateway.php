<?php

namespace App\Modules\Lab\Infrastructure\Integrations;

use App\Modules\Lab\Application\Contracts\LabProviderGateway;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabProviderDispatchData;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;
use Carbon\CarbonImmutable;

final readonly class ConfigLabProviderGateway implements LabProviderGateway
{
    public function __construct(
        private string $providerKey,
        private string $secret,
        private string $externalOrderPrefix,
    ) {}

    #[\Override]
    public function providerKey(): string
    {
        return $this->providerKey;
    }

    #[\Override]
    public function reconcileOrder(LabOrderData $order): LabProviderOrderPayload
    {
        return new LabProviderOrderPayload(
            externalOrderId: $order->externalOrderId ?? sprintf('%s-%s', $this->externalOrderPrefix, $order->orderId),
            status: $order->status,
            occurredAt: CarbonImmutable::now(),
            results: [],
        );
    }

    #[\Override]
    public function sendOrder(LabOrderData $order): LabProviderDispatchData
    {
        return new LabProviderDispatchData(
            externalOrderId: sprintf('%s-%s', $this->externalOrderPrefix, $order->orderId),
            occurredAt: CarbonImmutable::now(),
        );
    }

    #[\Override]
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->secret);

        return hash_equals($expected, trim($signature));
    }
}
