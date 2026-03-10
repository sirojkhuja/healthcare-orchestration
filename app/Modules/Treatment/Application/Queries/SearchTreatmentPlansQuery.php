<?php

namespace App\Modules\Treatment\Application\Queries;

use App\Modules\Treatment\Application\Data\TreatmentPlanSearchCriteria;

final readonly class SearchTreatmentPlansQuery
{
    public function __construct(
        public TreatmentPlanSearchCriteria $criteria,
    ) {}
}
