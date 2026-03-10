<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\DeleteTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class DeleteTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    public function handle(DeleteTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanAdministrationService->delete($command->planId);
    }
}
