<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class KafkaConsumerLagData
{
    /**
     * @param  list<string>  $topics
     */
    public function __construct(
        public string $consumerName,
        public array $topics,
        public int $processedTotal,
        public ?CarbonImmutable $lastProcessedAt,
        public int $receiptLagSeconds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'consumer_name' => $this->consumerName,
            'topics' => $this->topics,
            'processed_total' => $this->processedTotal,
            'last_processed_at' => $this->lastProcessedAt?->toIso8601String(),
            'receipt_lag_seconds' => $this->receiptLagSeconds,
        ];
    }
}
