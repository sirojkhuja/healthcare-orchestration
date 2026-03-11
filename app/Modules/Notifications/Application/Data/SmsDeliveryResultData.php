<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class SmsDeliveryResultData
{
    /**
     * @param  list<SmsDeliveryAttemptData>  $attempts
     */
    public function __construct(
        public bool $successful,
        public array $attempts,
        public ?string $providerKey = null,
        public ?string $providerName = null,
        public ?string $providerMessageId = null,
        public ?string $lastErrorCode = null,
        public ?string $lastErrorMessage = null,
        public ?CarbonImmutable $completedAt = null,
    ) {}

    public function attemptedCount(): int
    {
        return count($this->attempts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attemptsArray(): array
    {
        return array_map(
            static fn (SmsDeliveryAttemptData $attempt): array => $attempt->toArray(),
            $this->attempts,
        );
    }

    public function lastAttempt(): ?SmsDeliveryAttemptData
    {
        if ($this->attempts === []) {
            return null;
        }

        return $this->attempts[array_key_last($this->attempts)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->successful ? 'sent' : 'failed',
            'attempted_count' => $this->attemptedCount(),
            'provider' => $this->providerKey === null && $this->providerMessageId === null ? null : [
                'key' => $this->providerKey,
                'name' => $this->providerName,
                'message_id' => $this->providerMessageId,
            ],
            'attempts' => $this->attemptsArray(),
            'last_error' => $this->lastErrorCode === null && $this->lastErrorMessage === null ? null : [
                'code' => $this->lastErrorCode,
                'message' => $this->lastErrorMessage,
            ],
            'completed_at' => $this->completedAt?->toIso8601String(),
        ];
    }
}
