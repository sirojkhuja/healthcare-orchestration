<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\ProviderCalendarData;
use App\Modules\Scheduling\Application\Queries\GetProviderCalendarQuery;
use App\Modules\Scheduling\Application\Services\ProviderCalendarService;

final class GetProviderCalendarQueryHandler
{
    public function __construct(
        private readonly ProviderCalendarService $providerCalendarService,
    ) {}

    public function handle(GetProviderCalendarQuery $query): ProviderCalendarData
    {
        return $this->providerCalendarService->get(
            providerId: $query->providerId,
            dateFrom: $query->dateFrom,
            dateTo: $query->dateTo,
            limit: $query->limit,
        );
    }
}
