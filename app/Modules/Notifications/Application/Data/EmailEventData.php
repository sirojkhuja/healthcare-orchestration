<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EmailEventData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventId,
        public string $tenantId,
        public ?string $notificationId,
        public string $source,
        public string $eventType,
        public string $recipientEmail,
        public ?string $recipientName,
        public string $subject,
        public string $providerKey,
        public ?string $messageId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public array $metadata,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->eventId,
            'tenant_id' => $this->tenantId,
            'notification_id' => $this->notificationId,
            'source' => $this->source,
            'event_type' => $this->eventType,
            'recipient' => [
                'email' => $this->recipientEmail,
                'name' => $this->recipientName,
            ],
            'subject' => $this->subject,
            'provider' => [
                'key' => $this->providerKey,
                'message_id' => $this->messageId,
            ],
            'error' => $this->errorCode === null && $this->errorMessage === null ? null : [
                'code' => $this->errorCode,
                'message' => $this->errorMessage,
            ],
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
