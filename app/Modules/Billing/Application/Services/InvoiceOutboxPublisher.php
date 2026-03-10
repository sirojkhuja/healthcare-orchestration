<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class InvoiceOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishInvoiceEvent(string $eventType, InvoiceData $invoice, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.billing.v1',
            tenantId: $invoice->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $invoice->invoiceId,
            headers: [
                'tenant_id' => $invoice->tenantId,
                'object_id' => $invoice->invoiceId,
            ],
            payload: $payload + [
                'invoice' => $invoice->toArray(),
            ],
        ));
    }
}
