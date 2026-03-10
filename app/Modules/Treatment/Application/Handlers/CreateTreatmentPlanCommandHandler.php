<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\CreateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class CreateTreatmentPlanCommandHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    public function handle(CreateTreatmentPlanCommand $command): TreatmentPlanData
    {
        return $this->treatmentPlanAdministrationService->create($command->attributes);
    }
}
