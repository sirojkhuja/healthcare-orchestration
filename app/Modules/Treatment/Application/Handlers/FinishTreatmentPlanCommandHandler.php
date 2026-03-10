<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\FinishTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanWorkflowService;

final class FinishTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanWorkflowService $treatmentPlanWorkflowService,
    ) {}

    public function handle(FinishTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanWorkflowService->finish($command->planId);
    }
}
