<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\DeleteInsuranceRuleCommand;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Services\InsuranceRuleService;

final readonly class DeleteInsuranceRuleCommandHandler
{
    public function __construct(
        private InsuranceRuleService $service,
    ) {}

    public function handle(DeleteInsuranceRuleCommand $command): InsuranceRuleData
    {
        return $this->service->delete($command->ruleId);
    }
}
