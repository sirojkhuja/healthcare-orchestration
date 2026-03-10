<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\ResumeTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanWorkflowService;

final class ResumeTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanWorkflowService $treatmentPlanWorkflowService,
    ) {}

    public function handle(ResumeTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanWorkflowService->resume($command->planId);
    }
}
