<?php

namespace App\Modules\Observability\Application\Data;

final readonly class JobSearchCriteria
{
    public function __construct(
        public ?string $queue,
        public int $limit,
    ) {}
}
