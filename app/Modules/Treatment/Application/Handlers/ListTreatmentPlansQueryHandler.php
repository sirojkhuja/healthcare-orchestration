<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Queries\ListTreatmentPlansQuery;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class ListTreatmentPlansQueryHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    /**
     * @return list<TreatmentPlanData>
     */
    public function handle(ListTreatmentPlansQuery $query): array
    {
        return $this->treatmentPlanAdministrationService->list();
    }
}
