<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Queries\GetTreatmentPlanQuery;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class GetTreatmentPlanQueryHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    public function handle(GetTreatmentPlanQuery $query): TreatmentPlanData
    {
        return $this->treatmentPlanAdministrationService->get($query->planId);
    }
}
