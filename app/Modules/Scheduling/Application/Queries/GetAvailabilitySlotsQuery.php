<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class GetAvailabilitySlotsQuery
{
    public function __construct(
        public string $providerId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $limit = null,
    ) {}
}
