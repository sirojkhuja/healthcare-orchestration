<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class PaymentOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishPaymentEvent(string $eventType, PaymentData $payment, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.billing.v1',
            tenantId: $payment->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $payment->paymentId,
            headers: [
                'tenant_id' => $payment->tenantId,
                'object_id' => $payment->paymentId,
            ],
            payload: $payload + [
                'payment' => $payment->toArray(),
            ],
        ));
    }
}
