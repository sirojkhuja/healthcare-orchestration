<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class NotificationData
{
    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $notificationId,
        public string $tenantId,
        public string $templateId,
        public string $templateCode,
        public int $templateVersion,
        public string $channel,
        public array $recipient,
        public string $recipientValue,
        public ?string $renderedSubject,
        public string $renderedBody,
        public array $variables,
        public array $metadata,
        public string $status,
        public int $attempts,
        public int $maxAttempts,
        public ?string $providerKey,
        public ?string $providerMessageId,
        public ?string $lastErrorCode,
        public ?string $lastErrorMessage,
        public CarbonImmutable $queuedAt,
        public ?CarbonImmutable $sentAt,
        public ?CarbonImmutable $failedAt,
        public ?CarbonImmutable $canceledAt,
        public ?string $canceledReason,
        public ?CarbonImmutable $lastAttemptAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->notificationId,
            'tenant_id' => $this->tenantId,
            'template' => [
                'id' => $this->templateId,
                'code' => $this->templateCode,
                'version' => $this->templateVersion,
                'channel' => $this->channel,
            ],
            'recipient' => $this->recipient,
            'recipient_value' => $this->recipientValue,
            'rendered_subject' => $this->renderedSubject,
            'rendered_body' => $this->renderedBody,
            'variables' => $this->variables,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'attempts' => [
                'used' => $this->attempts,
                'max' => $this->maxAttempts,
                'remaining' => max(0, $this->maxAttempts - $this->attempts),
            ],
            'provider' => $this->providerKey === null && $this->providerMessageId === null ? null : [
                'key' => $this->providerKey,
                'message_id' => $this->providerMessageId,
            ],
            'failure' => $this->lastErrorCode === null && $this->lastErrorMessage === null ? null : [
                'code' => $this->lastErrorCode,
                'message' => $this->lastErrorMessage,
            ],
            'queued_at' => $this->queuedAt->toIso8601String(),
            'sent_at' => $this->sentAt?->toIso8601String(),
            'failed_at' => $this->failedAt?->toIso8601String(),
            'canceled_at' => $this->canceledAt?->toIso8601String(),
            'canceled_reason' => $this->canceledReason,
            'last_attempt_at' => $this->lastAttemptAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
