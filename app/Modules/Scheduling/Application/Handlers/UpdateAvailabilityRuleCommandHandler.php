<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\UpdateAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Services\AvailabilityRuleService;

final class UpdateAvailabilityRuleCommandHandler
{
    public function __construct(
        private readonly AvailabilityRuleService $availabilityRuleService,
    ) {}

    public function handle(UpdateAvailabilityRuleCommand $command): AvailabilityRuleData
    {
        return $this->availabilityRuleService->update(
            $command->providerId,
            $command->ruleId,
            $command->attributes,
        );
    }
}
