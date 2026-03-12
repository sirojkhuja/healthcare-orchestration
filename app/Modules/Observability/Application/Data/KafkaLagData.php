<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class KafkaLagData
{
    /**
     * @param  list<string>  $brokers
     * @param  list<KafkaConsumerLagData>  $consumers
     */
    public function __construct(
        public array $brokers,
        public string $consumerGroup,
        public array $consumers,
        public CarbonImmutable $capturedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'brokers' => $this->brokers,
            'consumer_group' => $this->consumerGroup,
            'consumers' => array_map(
                static fn (KafkaConsumerLagData $consumer): array => $consumer->toArray(),
                $this->consumers,
            ),
            'captured_at' => $this->capturedAt->toIso8601String(),
        ];
    }
}
