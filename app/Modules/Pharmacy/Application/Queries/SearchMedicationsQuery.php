<?php

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;

final readonly class SearchMedicationsQuery
{
    public function __construct(
        public MedicationListCriteria $criteria,
    ) {}
}
