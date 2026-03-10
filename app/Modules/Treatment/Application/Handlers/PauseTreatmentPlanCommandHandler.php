<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\PauseTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanWorkflowService;

final class PauseTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanWorkflowService $treatmentPlanWorkflowService,
    ) {}

    public function handle(PauseTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanWorkflowService->pause($command->planId, $command->reason);
    }
}
