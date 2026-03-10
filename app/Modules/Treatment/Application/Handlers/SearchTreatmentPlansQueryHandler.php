<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Queries\SearchTreatmentPlansQuery;
use App\Modules\Treatment\Application\Services\TreatmentPlanAdministrationService;

final class SearchTreatmentPlansQueryHandler
{
    public function __construct(
        private readonly TreatmentPlanAdministrationService $treatmentPlanAdministrationService,
    ) {}

    /**
     * @return list<TreatmentPlanData>
     */
    public function handle(SearchTreatmentPlansQuery $query): array
    {
        return $this->treatmentPlanAdministrationService->search($query->criteria);
    }
}
