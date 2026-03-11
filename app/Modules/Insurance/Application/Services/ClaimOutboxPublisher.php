<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\Insurance\Application\Data\ClaimData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class ClaimOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishClaimEvent(string $eventType, ClaimData $claim, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.claims.v1',
            tenantId: $claim->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $claim->claimId,
            headers: [
                'tenant_id' => $claim->tenantId,
                'object_id' => $claim->claimId,
            ],
            payload: $payload + [
                'claim' => $claim->toArray(),
            ],
        ));
    }
}
