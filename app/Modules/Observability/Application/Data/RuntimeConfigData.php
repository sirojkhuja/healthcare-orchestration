<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class RuntimeConfigData
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $brokers
     */
    public function __construct(
        public string $service,
        public string $environment,
        public string $version,
        public string $cacheStore,
        public string $queueConnection,
        public array $modules,
        public array $brokers,
        public string $consumerGroup,
        public int $outboxBatchSize,
        public int $outboxMaxAttempts,
        public ?CarbonImmutable $lastReloadedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'environment' => $this->environment,
            'version' => $this->version,
            'cache_store' => $this->cacheStore,
            'queue_connection' => $this->queueConnection,
            'modules' => $this->modules,
            'kafka' => [
                'brokers' => $this->brokers,
                'consumer_group' => $this->consumerGroup,
            ],
            'outbox' => [
                'batch_size' => $this->outboxBatchSize,
                'max_attempts' => $this->outboxMaxAttempts,
            ],
            'last_reloaded_at' => $this->lastReloadedAt?->toIso8601String(),
        ];
    }
}
