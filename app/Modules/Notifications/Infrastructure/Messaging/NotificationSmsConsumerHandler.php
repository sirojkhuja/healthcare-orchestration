<?php

namespace App\Modules\Notifications\Infrastructure\Messaging;

use App\Modules\Notifications\Application\Services\NotificationSmsDeliveryService;
use App\Shared\Application\Contracts\KafkaConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use LogicException;

final class NotificationSmsConsumerHandler implements KafkaConsumerHandler
{
    public function __construct(
        private readonly NotificationSmsDeliveryService $notificationSmsDeliveryService,
    ) {}

    #[\Override]
    public function consumerGroup(): string
    {
        return config()->string('medflow.kafka.group_id', 'medflow-local').'.notifications-sms';
    }

    #[\Override]
    public function consumerName(): string
    {
        return 'notifications.sms.delivery';
    }

    #[\Override]
    public function topics(): array
    {
        return ['medflow.notifications.v1'];
    }

    #[\Override]
    public function handle(ConsumedKafkaMessage $message): void
    {
        $eventType = $message->headers['event_type'] ?? null;

        if (! is_string($eventType) || ! in_array($eventType, ['notification.queued', 'notification.retried'], true)) {
            return;
        }

        $payload = is_array($message->payload) ? $message->payload : [];
        $notification = $payload['notification'] ?? null;

        if (! is_array($notification)) {
            throw new LogicException('Notification events must include a notification projection payload.');
        }

        $tenantId = $this->requiredString($notification['tenant_id'] ?? ($message->headers['tenant_id'] ?? null));
        $notificationId = $this->requiredString($notification['id'] ?? ($message->headers['object_id'] ?? null));

        $this->notificationSmsDeliveryService->deliver($tenantId, $notificationId);
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new LogicException('Notification events must provide both tenant_id and notification id.');
        }

        return trim($value);
    }
}
