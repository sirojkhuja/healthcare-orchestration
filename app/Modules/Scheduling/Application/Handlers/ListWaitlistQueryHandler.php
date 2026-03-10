<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\WaitlistEntryData;
use App\Modules\Scheduling\Application\Queries\ListWaitlistQuery;
use App\Modules\Scheduling\Application\Services\WaitlistService;

final class ListWaitlistQueryHandler
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    /**
     * @return list<WaitlistEntryData>
     */
    public function handle(ListWaitlistQuery $query): array
    {
        return $this->waitlistService->list($query->filters);
    }
}
