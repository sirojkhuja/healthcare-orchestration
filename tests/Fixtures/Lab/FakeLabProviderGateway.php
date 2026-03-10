<?php

namespace Tests\Fixtures\Lab;

use App\Modules\Lab\Application\Contracts\LabProviderGateway;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabProviderDispatchData;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;
use Carbon\CarbonImmutable;

final class FakeLabProviderGateway implements LabProviderGateway
{
    /**
     * @var array<string, LabProviderDispatchData>
     */
    private array $dispatches = [];

    /**
     * @var array<string, LabProviderOrderPayload>
     */
    private array $snapshots = [];

    public function __construct(
        private readonly string $providerKey,
        private readonly string $secret = 'fake-lab-secret',
    ) {}

    public function queueDispatch(string $orderId, string $externalOrderId, ?CarbonImmutable $occurredAt = null): void
    {
        $this->dispatches[$orderId] = new LabProviderDispatchData(
            externalOrderId: $externalOrderId,
            occurredAt: $occurredAt ?? CarbonImmutable::now(),
        );
    }

    public function queueSnapshot(string $orderId, LabProviderOrderPayload $payload): void
    {
        $this->snapshots[$orderId] = $payload;
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    #[\Override]
    public function providerKey(): string
    {
        return $this->providerKey;
    }

    #[\Override]
    public function reconcileOrder(LabOrderData $order): LabProviderOrderPayload
    {
        return $this->snapshots[$order->orderId]
            ?? new LabProviderOrderPayload(
                externalOrderId: $order->externalOrderId ?? sprintf('%s-%s', $this->providerKey, $order->orderId),
                status: $order->status,
                occurredAt: CarbonImmutable::now(),
                results: [],
            );
    }

    #[\Override]
    public function sendOrder(LabOrderData $order): LabProviderDispatchData
    {
        return $this->dispatches[$order->orderId]
            ?? new LabProviderDispatchData(
                externalOrderId: sprintf('%s-%s', $this->providerKey, $order->orderId),
                occurredAt: CarbonImmutable::now(),
            );
    }

    #[\Override]
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        return hash_equals($this->sign($payload), trim($signature));
    }
}
