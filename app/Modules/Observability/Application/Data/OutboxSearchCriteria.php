<?php

namespace App\Modules\Observability\Application\Data;

final readonly class OutboxSearchCriteria
{
    public function __construct(
        public ?string $status,
        public ?string $topic,
        public ?string $eventType,
        public int $limit,
    ) {}
}
