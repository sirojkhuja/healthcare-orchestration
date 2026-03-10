<?php

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;

final readonly class ListMedicationsQuery
{
    public function __construct(
        public MedicationListCriteria $criteria,
    ) {}
}
