<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\StartTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanWorkflowService;

final class StartTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanWorkflowService $treatmentPlanWorkflowService,
    ) {}

    public function handle(StartTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanWorkflowService->start($command->planId);
    }
}
