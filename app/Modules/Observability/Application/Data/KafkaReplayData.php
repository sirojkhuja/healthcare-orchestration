<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class KafkaReplayData
{
    /**
     * @param  list<string>|null  $eventIds
     */
    public function __construct(
        public string $consumerName,
        public ?array $eventIds,
        public ?CarbonImmutable $processedBefore,
        public int $limit,
        public int $clearedCount,
        public CarbonImmutable $performedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'consumer_name' => $this->consumerName,
            'event_ids' => $this->eventIds,
            'processed_before' => $this->processedBefore?->toIso8601String(),
            'limit' => $this->limit,
            'cleared_count' => $this->clearedCount,
            'performed_at' => $this->performedAt->toIso8601String(),
        ];
    }
}
