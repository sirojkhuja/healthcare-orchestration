<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\RebuildAvailabilityCacheCommand;
use App\Modules\Scheduling\Application\Data\AvailabilitySlotResultData;
use App\Modules\Scheduling\Application\Services\AvailabilitySlotService;

final class RebuildAvailabilityCacheCommandHandler
{
    public function __construct(
        private readonly AvailabilitySlotService $availabilitySlotService,
    ) {}

    public function handle(RebuildAvailabilityCacheCommand $command): AvailabilitySlotResultData
    {
        return $this->availabilitySlotService->rebuild(
            $command->providerId,
            $command->dateFrom,
            $command->dateTo,
            $command->limit,
        );
    }
}
