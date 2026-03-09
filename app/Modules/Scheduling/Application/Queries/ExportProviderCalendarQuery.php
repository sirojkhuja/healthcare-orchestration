<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class ExportProviderCalendarQuery
{
    public function __construct(
        public string $providerId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $limit = null,
        public string $format = 'csv',
    ) {}
}
