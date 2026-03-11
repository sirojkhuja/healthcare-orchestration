<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TelegramSendResultData
{
    public function __construct(
        public string $providerKey,
        public string $chatId,
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
            ],
            'chat_id' => $this->chatId,
            'status' => $this->status,
            'message_id' => $this->messageId,
            'error' => $this->errorCode === null && $this->errorMessage === null ? null : [
                'code' => $this->errorCode,
                'message' => $this->errorMessage,
            ],
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
