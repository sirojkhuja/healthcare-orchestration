<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\ApproveTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanWorkflowService;

final class ApproveTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanWorkflowService $treatmentPlanWorkflowService,
    ) {}

    public function handle(ApproveTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanWorkflowService->approve($command->planId);
    }
}
