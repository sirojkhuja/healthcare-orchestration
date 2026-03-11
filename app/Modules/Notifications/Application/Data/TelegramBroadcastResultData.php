<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class TelegramBroadcastResultData
{
    /**
     * @param  list<string>  $chatIds
     * @param  list<array<string, mixed>>  $results
     */
    public function __construct(
        public string $audience,
        public array $chatIds,
        public array $results,
        public int $sentCount,
        public int $failedCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'audience' => $this->audience,
            'chat_ids' => $this->chatIds,
            'sent_count' => $this->sentCount,
            'failed_count' => $this->failedCount,
            'results' => $this->results,
        ];
    }
}
