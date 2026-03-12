<?php

namespace App\Modules\Observability\Application\Commands;

use Carbon\CarbonImmutable;

final readonly class ReplayKafkaEventsCommand
{
    /**
     * @param  list<string>|null  $eventIds
     */
    public function __construct(
        public string $consumerName,
        public ?array $eventIds,
        public ?CarbonImmutable $processedBefore,
        public int $limit,
    ) {}
}
