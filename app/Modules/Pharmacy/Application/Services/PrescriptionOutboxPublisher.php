<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class PrescriptionOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishPrescriptionEvent(string $eventType, PrescriptionData $prescription, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.pharmacy.v1',
            tenantId: $prescription->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $prescription->prescriptionId,
            headers: [
                'tenant_id' => $prescription->tenantId,
                'object_id' => $prescription->prescriptionId,
            ],
            payload: $payload + [
                'prescription' => $prescription->toArray(),
            ],
        ));
    }
}
