<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EmailSendResultData
{
    public function __construct(
        public string $providerKey,
        public string $recipientEmail,
        public ?string $recipientName,
        public string $subject,
        public string $status,
        public CarbonImmutable $occurredAt,
        public ?string $messageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => [
                'key' => $this->providerKey,
                'message_id' => $this->messageId,
            ],
            'recipient' => [
                'email' => $this->recipientEmail,
                'name' => $this->recipientName,
            ],
            'subject' => $this->subject,
            'status' => $this->status,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'error' => $this->errorCode === null && $this->errorMessage === null ? null : [
                'code' => $this->errorCode,
                'message' => $this->errorMessage,
            ],
        ];
    }
}
