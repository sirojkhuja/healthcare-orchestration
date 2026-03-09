<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class RebuildAvailabilityCacheCommand
{
    public function __construct(
        public string $providerId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $limit = null,
    ) {}
}
