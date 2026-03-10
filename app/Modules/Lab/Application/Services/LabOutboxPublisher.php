<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabResultData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class LabOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishOrderEvent(string $eventType, LabOrderData $order, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.labs.v1',
            tenantId: $order->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $order->orderId,
            headers: [
                'tenant_id' => $order->tenantId,
                'object_id' => $order->orderId,
            ],
            payload: $payload + [
                'order' => $order->toArray(),
            ],
        ));
    }

    public function publishResultReceived(LabOrderData $order, LabResultData $result): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: 'lab_result.received',
            topic: 'medflow.labs.v1',
            tenantId: $order->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $order->orderId,
            headers: [
                'tenant_id' => $order->tenantId,
                'order_id' => $order->orderId,
                'result_id' => $result->resultId,
            ],
            payload: [
                'order' => $order->toArray(),
                'result' => $result->toArray(),
            ],
        ));
    }
}
