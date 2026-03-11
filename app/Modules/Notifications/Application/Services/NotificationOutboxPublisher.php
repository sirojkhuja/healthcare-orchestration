<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Data\NotificationData;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\OutboxMessage;
use Illuminate\Support\Str;

final class NotificationOutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RequestMetadataContext $requestMetadataContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishNotificationEvent(string $eventType, NotificationData $notification, array $payload = []): void
    {
        $metadata = $this->requestMetadataContext->current();

        $this->outboxRepository->enqueue(new OutboxMessage(
            outboxId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            topic: 'medflow.notifications.v1',
            tenantId: $notification->tenantId,
            requestId: $metadata->requestId,
            correlationId: $metadata->correlationId,
            causationId: $metadata->causationId,
            partitionKey: $notification->notificationId,
            headers: [
                'tenant_id' => $notification->tenantId,
                'object_id' => $notification->notificationId,
            ],
            payload: $payload + [
                'notification' => $notification->toArray(),
            ],
        ));
    }
}
