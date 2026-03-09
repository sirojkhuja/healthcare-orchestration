<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\CreateAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Services\AvailabilityRuleService;

final class CreateAvailabilityRuleCommandHandler
{
    public function __construct(
        private readonly AvailabilityRuleService $availabilityRuleService,
    ) {}

    public function handle(CreateAvailabilityRuleCommand $command): AvailabilityRuleData
    {
        return $this->availabilityRuleService->create($command->providerId, $command->attributes);
    }
}
