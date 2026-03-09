<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AvailabilitySlotResultData;
use App\Modules\Scheduling\Application\Queries\GetAvailabilitySlotsQuery;
use App\Modules\Scheduling\Application\Services\AvailabilitySlotService;

final class GetAvailabilitySlotsQueryHandler
{
    public function __construct(
        private readonly AvailabilitySlotService $availabilitySlotService,
    ) {}

    public function handle(GetAvailabilitySlotsQuery $query): AvailabilitySlotResultData
    {
        return $this->availabilitySlotService->get(
            $query->providerId,
            $query->dateFrom,
            $query->dateTo,
            $query->limit,
        );
    }
}
