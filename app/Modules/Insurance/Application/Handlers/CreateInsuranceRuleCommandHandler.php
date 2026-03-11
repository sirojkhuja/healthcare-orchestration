<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\CreateInsuranceRuleCommand;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Services\InsuranceRuleService;

final readonly class CreateInsuranceRuleCommandHandler
{
    public function __construct(
        private InsuranceRuleService $service,
    ) {}

    public function handle(CreateInsuranceRuleCommand $command): InsuranceRuleData
    {
        return $this->service->create($command->attributes);
    }
}
