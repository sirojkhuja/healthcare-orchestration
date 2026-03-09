<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\DeleteAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Services\AvailabilityRuleService;

final class DeleteAvailabilityRuleCommandHandler
{
    public function __construct(
        private readonly AvailabilityRuleService $availabilityRuleService,
    ) {}

    public function handle(DeleteAvailabilityRuleCommand $command): AvailabilityRuleData
    {
        return $this->availabilityRuleService->delete($command->providerId, $command->ruleId);
    }
}
