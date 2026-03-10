<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\UpdateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class UpdateTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    public function handle(UpdateTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanAdministrationService->update($command->planId, $command->attributes);
    }
}
