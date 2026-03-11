<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\UpdateInsuranceRuleCommand;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Services\InsuranceRuleService;

final readonly class UpdateInsuranceRuleCommandHandler
{
    public function __construct(
        private InsuranceRuleService $service,
    ) {}

    public function handle(UpdateInsuranceRuleCommand $command): InsuranceRuleData
    {
        return $this->service->update($command->ruleId, $command->attributes);
    }
}
