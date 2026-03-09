<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class ListAvailabilityRulesQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
