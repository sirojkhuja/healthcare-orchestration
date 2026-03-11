<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class SmsDeliveryAttemptData
{
    public function __construct(
        public string $providerKey,
        public string $providerName,
        public string $status,
        public CarbonImmutable $occurredAt,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'provider_name' => $this->providerName,
            'status' => $this->status,
            'provider_message_id' => $this->providerMessageId,
            'error' => $this->errorCode === null && $this->errorMessage === null ? null : [
                'code' => $this->errorCode,
                'message' => $this->errorMessage,
            ],
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
